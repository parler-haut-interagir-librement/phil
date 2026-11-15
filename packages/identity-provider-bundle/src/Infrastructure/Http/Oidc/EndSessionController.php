<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http\Oidc;

use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\QueryString;
use Phil\IdentityProviderBundle\Infrastructure\Http\Pages;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RP-initiated logout (OpenID Connect RP-Initiated Logout 1.0), `GET` or `POST`: with an `id_token_hint` the provider
 * issued for the account signed in, it signs out at once; otherwise it asks the person to confirm, so that a link
 * cannot sign anyone out. It then goes back to the `post_logout_redirect_uri` when the client registered it, with the
 * state, or shows that the person is signed out.
 */
final readonly class EndSessionController
{
    public const string END_SESSION = 'phil_identity_provider_oidc_end_session';

    public const string CSRF_ID = 'phil_identity_provider_end_session';

    public function __construct(
        private Tokens $tokens,
        private ClientRegistry $clients,
        private Pages $pages,
        private Security $security,
    ) {
    }

    public function endSession(Request $request): Response
    {
        $parameters = $request->isMethod('POST') ? $request->request : $request->query;
        $hint = $parameters->getString('id_token_hint');
        $claims = '' === $hint ? null : $this->tokens->idTokenHint($hint);
        $audience = \is_array($claims) && \is_string($claims['aud'] ?? null) ? $claims['aud'] : null;
        $clientId = '' === $parameters->getString('client_id') ? $audience : $parameters->getString('client_id');
        $redirect = $this->redirect($clientId, $audience, $parameters->getString('post_logout_redirect_uri'), $parameters->getString('state'));
        $account = $this->pages->account();
        $confirmed = $request->isMethod('POST') && 'yes' === $request->request->getString('confirm');

        if ($confirmed) {
            $this->pages->checkCsrf($request, self::CSRF_ID);
        }

        if ($account instanceof Account && !$confirmed && ($claims['sub'] ?? null) !== $account->id()) {
            return $this->pages->render('oidc/logout_confirm', ['parameters' => array_filter(['id_token_hint' => $hint, 'client_id' => $clientId ?? '', 'post_logout_redirect_uri' => $parameters->getString('post_logout_redirect_uri'), 'state' => $parameters->getString('state')], static fn (string $value): bool => '' !== $value), 'csrf_id' => self::CSRF_ID]);
        }

        if ($account instanceof Account) {
            $this->security->logout(false);
        }

        return null === $redirect ? $this->pages->render('oidc/logged_out') : Pages::noStore(new RedirectResponse($redirect));
    }

    private function redirect(?string $clientId, ?string $audience, string $uri, string $state): ?string
    {
        if ('' === $uri || null === $clientId || (null !== $audience && $audience !== $clientId)) {
            return null;
        }

        $client = $this->clients->get($clientId);

        if (!$client instanceof Client || $client->isDisabled() || !$client->allowsPostLogoutRedirect($uri)) {
            return null;
        }

        return '' === $state ? $uri : QueryString::append($uri, ['state' => $state]);
    }
}
