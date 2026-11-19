<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Http;

use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Application\IssuedToken;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Phil\ApiKeysBundle\Infrastructure\Form\CreateTokenType;
use Psr\Clock\ClockInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The page where a signed-in member sees their tokens, creates one — its text shown on the answer to the form, once,
 * and never again — and revokes one; and the same page for the tokens of another owner — an organisation, an
 * application — at `/owners/{ownerType}/{owner}`, open only to whom the application's voters grant `MANAGE` on that
 * `TokenOwner`: without such a voter, nobody. Every answer is `Cache-Control: no-store`; revoking needs the CSRF token
 * of that token's row and redirects back to the page.
 */
final readonly class TokenPageController
{
    public const string ROUTE = 'phil_api_keys';

    public const string REVOKE_ROUTE = 'phil_api_keys_revoke';

    public const string OWNER_ROUTE = 'phil_api_keys_owner';

    public const string OWNER_REVOKE_ROUTE = 'phil_api_keys_owner_revoke';

    public const string REVOKE_CSRF_PREFIX = 'phil_api_keys_revoke_';

    /** The attribute a voter grants on a `TokenOwner` to whom may manage its tokens. */
    public const string MANAGE = 'PHIL_API_KEYS_MANAGE';

    public function __construct(
        private ApiTokens $tokens,
        private ScopeCatalogue $scopes,
        private FormFactoryInterface $forms,
        private Environment $twig,
        private TokenStorageInterface $tokenStorage,
        private CsrfTokenManagerInterface $csrf,
        private UrlGeneratorInterface $urls,
        private ClockInterface $clock,
        private string $template,
        private string $scopeTranslationDomain,
        private ?AuthorizationCheckerInterface $authorization = null,
        private OwnerTypes $owners = new OwnerTypes(),
    ) {
    }

    /**
     * @throws AccessDeniedException when no member is signed in, so that the firewall asks them to, or the member may
     *                               not manage the owner's tokens
     * @throws NotFoundHttpException when the owner type is not declared or the owner is out of shape
     */
    public function page(Request $request, ?string $ownerType = null, ?string $owner = null): Response
    {
        $tokenOwner = $this->owner($ownerType, $owner);
        $options = ['scopes' => $this->owners->scopes($tokenOwner->type)];
        $form = $this->forms->create(CreateTokenType::class, null, $options);
        $form->handleRequest($request);

        $issued = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, scopes: list<string>, lifetime: int} $data */
            $data = $form->getData();
            $issued = $this->tokens->create($tokenOwner, $data['name'], $data['scopes'], $this->clock->now()->modify(\sprintf('+%d days', $data['lifetime'])));
            // A fresh form, so that the page shows the new token above an empty one.
            $form = $this->forms->create(CreateTokenType::class, null, $options);
        }

        return $this->render($request, $tokenOwner, null !== $ownerType, $form->createView(), $issued, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    /**
     * @throws AccessDeniedException     when no member is signed in, or the member may not manage the owner's tokens
     * @throws AccessDeniedHttpException when the CSRF token does not match
     * @throws NotFoundHttpException     when the token is not the owner's
     */
    public function revoke(Request $request, string $id, ?string $ownerType = null, ?string $owner = null): RedirectResponse
    {
        $tokenOwner = $this->owner($ownerType, $owner);

        if (!$this->csrf->isTokenValid(new CsrfToken(self::REVOKE_CSRF_PREFIX.$id, (string) $request->request->get('_token')))) {
            throw new AccessDeniedHttpException('The form has expired: reload the page and try again.');
        }

        try {
            $this->tokens->revoke($id, $tokenOwner);
        } catch (TokenNotFound $tokenNotFound) {
            throw new NotFoundHttpException('No such token.', $tokenNotFound);
        }

        $url = null === $ownerType
            ? $this->urls->generate(self::ROUTE, ['revoked' => 1])
            : $this->urls->generate(self::OWNER_ROUTE, ['ownerType' => $tokenOwner->type, 'owner' => $tokenOwner->id, 'revoked' => 1]);
        $response = new RedirectResponse($url, Response::HTTP_SEE_OTHER);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * The signed-in member, or the owner the route names when the member may manage its tokens.
     *
     * @throws AccessDeniedException
     * @throws NotFoundHttpException
     */
    private function owner(?string $type, ?string $id): TokenOwner
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException('Sign in to manage your API tokens.');
        }

        if (null === $type) {
            return TokenOwner::user($user->getUserIdentifier());
        }

        try {
            $owner = new TokenOwner($type, (string) $id);
        } catch (InvalidTokenValue $invalidTokenValue) {
            throw new NotFoundHttpException('No such owner.', $invalidTokenValue);
        }

        if (!$this->owners->has($owner->type)) {
            throw new NotFoundHttpException('No such owner.');
        }

        if (true !== $this->authorization?->isGranted(self::MANAGE, $owner)) {
            throw new AccessDeniedException('You may not manage these API tokens.');
        }

        return $owner;
    }

    private function render(Request $request, TokenOwner $owner, bool $typed, FormView $form, ?IssuedToken $issued, int $status): Response
    {
        $labels = [];

        foreach ($this->scopes->all() as $scope) {
            $labels[$scope->name] = $scope->label;
        }

        $response = new Response($this->twig->render($this->template, [
            'tokens' => $this->tokens->find($owner),
            'owner' => $owner,
            'typed_owner' => $typed,
            'form' => $form,
            'issued' => $issued,
            'revoked' => $request->query->getBoolean('revoked'),
            'now' => $this->clock->now(),
            'scope_labels' => $labels,
            'scope_domain' => $this->scopeTranslationDomain,
        ]), $status);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
