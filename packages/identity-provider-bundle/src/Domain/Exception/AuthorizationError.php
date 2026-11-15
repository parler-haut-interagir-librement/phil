<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Exception;

use Phil\IdentityProviderBundle\Domain\Oidc\QueryString;
use RuntimeException;

/**
 * A refused authorization request, with its OAuth 2.0 error code (RFC 6749, section 4.1.2.1; OpenID Connect Core,
 * section 3.1.2.6). When the client and its redirect address are known, the error goes back to the client with the
 * state; otherwise — an unknown client, a redirect address it did not register — it is shown to the person and never
 * redirected, so that the provider cannot be used to send people anywhere.
 */
final class AuthorizationError extends RuntimeException implements IdentityProviderException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description,
        public readonly ?string $redirectUri,
        public readonly ?string $state,
    ) {
        parent::__construct($error.': '.$description);
    }

    public static function shown(string $error, string $description): self
    {
        return new self($error, $description, null, null);
    }

    public static function redirected(string $error, string $description, string $redirectUri, ?string $state): self
    {
        return new self($error, $description, $redirectUri, $state);
    }

    public function isRedirectable(): bool
    {
        return null !== $this->redirectUri;
    }

    /**
     * The address the error goes back to, with its parameters in the query.
     */
    public function redirectTo(string $issuer): string
    {
        return QueryString::append((string) $this->redirectUri, array_filter([
            'error' => $this->error,
            'error_description' => $this->description,
            'state' => $this->state,
            'iss' => $issuer,
        ], static fn (?string $value): bool => null !== $value));
    }
}
