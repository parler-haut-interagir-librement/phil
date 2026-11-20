<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Security;

use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\InvalidCredentials;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Psr\Container\ContainerInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The token handler of Symfony's `access_token` authenticator: the token a client presents, verified by
 * `ApiTokens::verify()`, gives the badge of its owner. A member, of the `user` type, is loaded by the firewall's user
 * provider; an owner of another type — an organisation, an application — by the user provider the configuration names
 * for that type, which gives the security token the user object of that owner. A token of a type no provider loads
 * opens nothing.
 *
 * The badge carries the token's scopes as the `scope` claim, which the authenticator puts on the security token as
 * `oauth2_scope`, and the token's identifier, which `TokenAuthenticationListener` puts on the security token and the
 * request beside the roles of the scopes. A refused token fails the authentication without saying why.
 *
 * It keeps no state between calls: one instance serves every request of a worker.
 */
final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    /** The attribute of the security token and of the request that holds the token's identifier. */
    public const string TOKEN_ID_ATTRIBUTE = '_api_token_id';

    /** The attribute of the security token and of the request that holds the type of the token's owner. */
    public const string OWNER_TYPE_ATTRIBUTE = '_api_token_owner_type';

    /** The attribute of the request that holds the token's scopes. */
    public const string SCOPES_ATTRIBUTE = '_api_token_scopes';

    /** The claim of the badge that holds the token's scopes, as Symfony's authenticator reads it. */
    public const string SCOPE_CLAIM = 'scope';

    public const string SCOPE_ROLE_PREFIX = 'ROLE_API_SCOPE_';

    /**
     * @param RequestStack|null       $requests  where the client's address is read, for the last use of the token
     * @param ContainerInterface|null $providers the user providers of the owner types other than `user`, by type
     */
    public function __construct(
        private ApiTokens $tokens,
        private ?RequestStack $requests = null,
        private ?ContainerInterface $providers = null,
    ) {
    }

    /**
     * The role of a scope: `projects:write` gives `ROLE_API_SCOPE_PROJECTS_WRITE`.
     */
    public static function scopeRole(string $scope): string
    {
        return self::SCOPE_ROLE_PREFIX.strtoupper((string) preg_replace('/[^a-z0-9]/i', '_', $scope));
    }

    /**
     * @throws BadCredentialsException when the token opens nothing: malformed, unknown, revoked or expired, or of an
     *                                 owner type no user provider loads
     */
    #[Override]
    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        try {
            $token = $this->tokens->verify($accessToken, $this->requests?->getMainRequest()?->getClientIp());
        } catch (InvalidCredentials $invalidCredentials) {
            throw new BadCredentialsException('The API token was refused.', 0, $invalidCredentials);
        }

        return new UserBadge($token->owner(), $this->loader($token->ownerType()), [
            self::SCOPE_CLAIM => $token->scopes(),
            self::TOKEN_ID_ATTRIBUTE => $token->id(),
            self::OWNER_TYPE_ATTRIBUTE => $token->ownerType(),
        ]);
    }

    /**
     * The loader of an owner type: none for a member, whom the firewall's provider loads; the type's provider otherwise.
     *
     * @return (callable(string): UserInterface)|null
     *
     * @throws BadCredentialsException when no user provider loads the type
     */
    private function loader(string $type): ?callable
    {
        if (TokenOwner::USER === $type) {
            return null;
        }

        $provider = true === $this->providers?->has($type) ? $this->providers->get($type) : null;

        if (!$provider instanceof UserProviderInterface) {
            throw new BadCredentialsException('The API token was refused.');
        }

        return $provider->loadUserByIdentifier(...);
    }
}
