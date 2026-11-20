<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Security;

use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * What a request authenticated by an access token carries, beside what Symfony's `access_token`
 * authenticator gives:
 *
 * - on the security token, a role per scope, `ROLE_API_SCOPE_` and the scope in capitals with every other character an
 *   underscore; the owner's roles too, unless the configuration leaves them out; the token's identifier and the type of
 *   its owner, as the attributes `_api_token_id` and `_api_token_owner_type`, next to the scopes Symfony keeps as
 *   `oauth2_scope`;
 * - on the request, the identifier, the owner type and the scopes, as the attributes `_api_token_id`,
 *   `_api_token_owner_type` and `_api_token_scopes`.
 *
 * Only an authentication whose badge `ApiTokenHandler` wrote is touched: every other one is left as it is.
 */
final readonly class TokenAuthenticationListener
{
    public function __construct(
        private bool $grantUserRoles = true,
    ) {
    }

    public function onAuthenticationTokenCreated(AuthenticationTokenCreatedEvent $event): void
    {
        $badge = $event->getPassport()->getBadge(UserBadge::class);
        $token = $event->getAuthenticatedToken();
        $attributes = $badge instanceof UserBadge ? $badge->getAttributes() ?? [] : [];
        $id = $attributes[ApiTokenHandler::TOKEN_ID_ATTRIBUTE] ?? null;

        $user = $token->getUser();

        if (!\is_string($id) || !$token instanceof PostAuthenticationToken || !$user instanceof UserInterface) {
            return;
        }

        $scopes = $this->strings($attributes[ApiTokenHandler::SCOPE_CLAIM] ?? []);
        $roles = array_map(ApiTokenHandler::scopeRole(...), $scopes);

        if ($this->grantUserRoles) {
            $roles = [...$token->getRoleNames(), ...$roles];
        }

        $authenticated = new PostAuthenticationToken($user, $token->getFirewallName(), array_values(array_unique($roles)));
        $authenticated->setAttributes($token->getAttributes());
        $authenticated->setAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE, $scopes);
        $authenticated->setAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, $id);
        $authenticated->setAttribute(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE, $attributes[ApiTokenHandler::OWNER_TYPE_ATTRIBUTE] ?? TokenOwner::USER);

        $event->setAuthenticatedToken($authenticated);
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $token = $event->getAuthenticatedToken();

        if (!$token->hasAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE)) {
            return;
        }

        $request = $event->getRequest();
        $request->attributes->set(ApiTokenHandler::TOKEN_ID_ATTRIBUTE, $token->getAttribute(ApiTokenHandler::TOKEN_ID_ATTRIBUTE));
        $request->attributes->set(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE, $token->hasAttribute(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE) ? $token->getAttribute(ApiTokenHandler::OWNER_TYPE_ATTRIBUTE) : TokenOwner::USER);
        $request->attributes->set(ApiTokenHandler::SCOPES_ATTRIBUTE, $this->strings($token->hasAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE) ? $token->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE) : []));
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return \is_array($value) ? array_values(array_filter($value, \is_string(...))) : [];
    }
}
