<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The attributes the session's security token carries: those of the `oidc_login` authenticator, and the bundle's.
 */
final class TokenAttribute
{
    public const string ID_TOKEN = 'oidc_id_token';

    public const string ACCESS_TOKEN = 'oauth2_access_token';

    public const string REFRESH_TOKEN = 'oauth2_refresh_token';

    public const string ACCESS_TOKEN_EXPIRES_AT = 'oauth2_access_token_expires_at';

    public const string PROVIDER_SESSION = 'oidc_sid';

    public const string ASSURANCE = 'oidc_acr';

    public const string SESSION_STARTED_AT = 'phil_identity_client_session_started_at';

    /**
     * The attributes a refresh can change.
     */
    public const array REFRESHED = [self::ID_TOKEN, self::ACCESS_TOKEN, self::REFRESH_TOKEN, self::ACCESS_TOKEN_EXPIRES_AT, self::PROVIDER_SESSION];

    public static function string(TokenInterface $token, string $name): ?string
    {
        $value = $token->hasAttribute($name) ? $token->getAttribute($name) : null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public static function integer(TokenInterface $token, string $name): ?int
    {
        $value = $token->hasAttribute($name) ? $token->getAttribute($name) : null;

        return is_numeric($value) ? (int) $value : null;
    }
}
