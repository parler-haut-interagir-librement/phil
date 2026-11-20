<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

/**
 * How a client proves itself at the token, revocation and introspection endpoints (OpenID Connect Core, section 9;
 * RFC 7523): its secret in HTTP Basic or in the form body, a JSON Web Token signed with a private key whose public key
 * it registered, or nothing — a public client, PKCE standing for the secret.
 */
enum ClientAuthMethod: string
{
    case ClientSecretBasic = 'client_secret_basic';
    case ClientSecretPost = 'client_secret_post';
    case PrivateKeyJwt = 'private_key_jwt';
    case None = 'none';

    /** Every method, as a constant expression for defaults. */
    public const array CASES = [self::ClientSecretBasic, self::ClientSecretPost, self::PrivateKeyJwt, self::None];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $method): string => $method->value, self::cases());
    }

    public function usesSecret(): bool
    {
        return self::ClientSecretBasic === $this || self::ClientSecretPost === $this;
    }
}
