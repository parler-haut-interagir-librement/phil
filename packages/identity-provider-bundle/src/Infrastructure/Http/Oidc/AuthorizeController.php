<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequest;
use Phil\IdentityProviderBundle\Domain\Oidc\QueryString;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Phil\IdentityProviderBundle\Infrastructure\Security\Proofs;
use Phil\IdentityProviderBundle\Infrastructure\Security\ReAuthenticationEntryPoint;
use Phil\IdentityProviderBundle\Infrastructure\Security\SecondFactorListener;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * The authorization endpoint (OpenID Connect Core, section 3.1.2), `GET` or `POST`:
 *
 * 1. the request is read; an error about the client or its address is shown, any other goes back to the client;
 * 2. nobody signed in: the firewall asks them to sign in and comes back here — or, with `prompt=none`,
 *    `login_required` goes back;
 * 3. `prompt=login`, or an authentication older than `max_age`: the person proves it again, then comes back;
 *
 * Before steps 2 and 3, the request is kept as the firewall's target path, in its `GET` form, so that any way of
 * signing in — the application's form included, and after a `POST` request too — comes back to it.
 * 4. a consent missing for the scopes asked: the consent page — or, with `prompt=none`, `consent_required`;
 * 5. a code goes back, with the state and the issuer.
 */
final readonly class AuthorizeController
{
    use TargetPathTrait;

    public const string AUTHORIZE = 'phil_identity_provider_oidc_authorize';

    public const string CONSENT = 'phil_identity_provider_oidc_consent';

    public const string CSRF_ID = 'phil_identity_provider_consent';

    public function __construct(
        private Authorization $authorization,
        private Pages $pages,
        private TokenStorageInterface $tokens,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $issuer,
        private ?Security $security = null,
    ) {
    }

    public function authorize(Request $request): Response
    {
        try {
            $authorization = $this->authorization->parse($request->isMethod('POST') ? $request->request->all() : $request->query->all());
        } catch (AuthorizationError $authorizationError) {
            return $this->refuse($authorizationError);
        }

        $account = $this->pages->account();

        if (!$account instanceof Account) {
            if ($authorization->prompts('none')) {
                return $this->back($this->authorization->errorRedirect($authorization, 'login_required'));
            }

            $this->keepTarget($request, $authorization->toQuery());

            throw new AccessDeniedException('Sign in to continue to the application.');
        }

        $authTime = $this->authTime();
        // A session that holds no proof, such as one restored by remember_me, cannot tell when its person last signed in.
        $unproven = [] === Proofs::of($this->tokens->getToken());

        if ($authorization->prompts('login') || (null !== $authorization->maxAge && ($unproven || $authTime->getTimestamp() + $authorization->maxAge < $this->clock->now()->getTimestamp()))) {
            if ($authorization->prompts('none')) {
                return $this->back($this->authorization->errorRedirect($authorization, 'login_required'));
            }

            return $this->reauthenticate($request, $authorization);
        }

        if ($this->authorization->needsConsent($account, $authorization)) {
            if ($authorization->prompts('none')) {
                return $this->back($this->authorization->errorRedirect($authorization, 'consent_required'));
            }

            return $this->pages->render('oidc/consent', ['client' => $this->authorization->client($authorization), 'request' => $authorization, 'parameters' => $authorization->toQuery(), 'csrf_id' => self::CSRF_ID]);
        }

        return $this->issue($request, $authorization, $account, $authTime);
    }

    public function consent(Request $request): Response
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request, self::CSRF_ID);
        $parameters = $request->request->all();
        unset($parameters['_token'], $parameters['decision']);
        $parameters['prompt'] = implode(' ', array_diff(explode(' ', \is_string($parameters['prompt'] ?? null) ? $parameters['prompt'] : ''), ['consent']));

        try {
            $authorization = $this->authorization->parse($parameters);
        } catch (AuthorizationError $authorizationError) {
            return $this->refuse($authorizationError);
        }

        if ('allow' !== $request->request->getString('decision')) {
            return $this->back($this->authorization->errorRedirect($authorization, 'access_denied'));
        }

        $this->authorization->grantConsent($account, $authorization);

        return $this->issue($request, $authorization, $account, $this->authTime());
    }

    private function issue(Request $request, AuthorizationRequest $authorization, Account $account, DateTimeImmutable $authTime): RedirectResponse
    {
        return $this->back($this->authorization->issueCode($authorization, $account, $authTime, array_keys(Proofs::of($this->tokens->getToken())), SessionListener::id($request)));
    }

    /**
     * When the account last proved a method: the latest proof of its token.
     */
    private function authTime(): DateTimeImmutable
    {
        $proofs = Proofs::of($this->tokens->getToken());

        return [] === $proofs ? $this->clock->now() : new DateTimeImmutable()->setTimestamp(max($proofs));
    }

    private function reauthenticate(Request $request, AuthorizationRequest $authorization): Response
    {
        $parameters = $authorization->toQuery();
        $parameters['prompt'] = implode(' ', array_diff($authorization->prompt, ['login']));
        $target = $this->keepTarget($request, $parameters);

        if (null !== $target) {
            $request->getSession()->set(SecondFactorListener::TARGET, $target);
        }

        return Pages::noStore(new RedirectResponse($this->urls->generate(ReAuthenticationEntryPoint::ROUTE)));
    }

    /**
     * Keeps the authorization request, without its empty parameters, as where the sign-in comes back to.
     *
     * @param array<string, string> $parameters
     *
     * @return string|null the address kept, null without a session
     */
    private function keepTarget(Request $request, array $parameters): ?string
    {
        if (!$request->hasSession()) {
            return null;
        }

        $target = QueryString::append($this->urls->generate(self::AUTHORIZE), array_filter($parameters, static fn (string $value): bool => '' !== $value));
        $firewall = $this->security?->getFirewallConfig($request)?->getName();

        if (null !== $firewall) {
            $this->saveTargetPath($request->getSession(), $firewall, $target);
        }

        return $target;
    }

    private function refuse(AuthorizationError $error): Response
    {
        if ($error->isRedirectable()) {
            return $this->back($error->redirectTo($this->issuer));
        }

        return $this->pages->render('oidc/error', ['error' => $error->error, 'description' => $error->description], Response::HTTP_BAD_REQUEST);
    }

    private function back(string $url): RedirectResponse
    {
        return Pages::noStore(new RedirectResponse($url, Response::HTTP_FOUND));
    }
}
