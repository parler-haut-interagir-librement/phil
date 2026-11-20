<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Exception;

use Phil\IdentityProviderBundle\Domain\Exception\IdentityProviderException;
use RuntimeException;

/**
 * An error of the token, revocation, introspection or userinfo endpoint, with its OAuth 2.0 code and HTTP status
 * (RFC 6749, section 5.2; RFC 6750, section 3.1).
 */
final class OAuthError extends RuntimeException implements IdentityProviderException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly int $status,
    ) {
        parent::__construct($error.': '.$description);
    }

    public static function invalidRequest(string $description): self
    {
        return new self('invalid_request', $description, 400);
    }

    public static function invalidClient(): self
    {
        return new self('invalid_client', 'The client could not be authenticated.', 401);
    }

    public static function invalidGrant(string $description): self
    {
        return new self('invalid_grant', $description, 400);
    }

    public static function unsupportedGrantType(): self
    {
        return new self('unsupported_grant_type', 'Only authorization_code, refresh_token and client_credentials are supported.', 400);
    }

    public static function unauthorizedClient(string $description): self
    {
        return new self('unauthorized_client', $description, 400);
    }

    /**
     * A resource the client may not ask a token for (RFC 8707, section 2).
     */
    public static function invalidTarget(string $description): self
    {
        return new self('invalid_target', $description, 400);
    }

    public static function invalidScope(string $description): self
    {
        return new self('invalid_scope', $description, 400);
    }

    public static function invalidToken(): self
    {
        return new self('invalid_token', 'The access token is invalid, expired or revoked.', 401);
    }

    /**
     * @return array{error: string, error_description: string}
     */
    public function toArray(): array
    {
        return ['error' => $this->error, 'error_description' => $this->description];
    }
}
