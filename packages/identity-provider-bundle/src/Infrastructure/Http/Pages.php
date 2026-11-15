<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * What the pages of the bundle share: the signed-in account, the attribute a sensitive action needs — whose refusal
 * starts Symfony Security's re-authentication —, the CSRF check of every form, and answers that no cache keeps.
 */
final readonly class Pages
{
    public const string CSRF_ID = 'phil_identity_provider';

    public function __construct(
        private Environment $twig,
        private TokenStorageInterface $tokens,
        private AuthorizationCheckerInterface $authorization,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private string $stepUpAttribute,
    ) {
    }

    public function account(): ?Account
    {
        $user = $this->tokens->getToken()?->getUser();

        return $user instanceof AccountUser ? $user->account : null;
    }

    /**
     * @throws AccessDeniedException when nobody is signed in, so that the firewall asks them to
     */
    public function requireAccount(): Account
    {
        return $this->account() ?? throw new AccessDeniedException('Sign in first.');
    }

    /**
     * The signed-in account, once it proved its strongest method recently.
     *
     * @throws AccessDeniedException carrying the vote that asks for a re-authentication
     */
    public function requireStepUp(): Account
    {
        $account = $this->requireAccount();
        $decision = new AccessDecision();

        if (!$this->authorization->isGranted($this->stepUpAttribute, null, $decision)) {
            $exception = new AccessDeniedException('Prove it is you again.');
            $exception->setAttributes([$this->stepUpAttribute]);
            $exception->setAccessDecision($decision);

            throw $exception;
        }

        return $account;
    }

    /**
     * @throws AccessDeniedHttpException when the form's token does not match
     */
    public function checkCsrf(Request $request, string $id = self::CSRF_ID): void
    {
        if (!$this->isCsrfValid($request->request->getString('_token'), $id)) {
            throw new AccessDeniedHttpException('The form has expired: reload the page and try again.');
        }
    }

    /**
     * Whether a token — of a form, or of the `X-CSRF-Token` header a script sends — matches.
     */
    public function isCsrfValid(?string $token, string $id = self::CSRF_ID): bool
    {
        return null !== $token && '' !== $token && $this->csrf->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK): Response
    {
        $response = new Response($this->twig->render('@PhilIdentityProvider/'.$template.'.html.twig', $context + ['layout' => $this->layout, 'account' => $this->account()]), $status);

        return self::noStore($response);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    public function redirect(string $route, array $parameters = []): RedirectResponse
    {
        return self::noStore(new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER));
    }

    /**
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    public static function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
