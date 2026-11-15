<?php

declare(strict_types=1);

namespace Phil\Lti\Exception;

use RuntimeException;

/**
 * A request to the token endpoint of the platform that is refused, with the error code of RFC 6749, section 5.2:
 * `invalid_request`, `invalid_client`, `invalid_grant`, `unauthorized_client`, `unsupported_grant_type` or
 * `invalid_scope`.
 */
final class TokenRequestRefused extends RuntimeException implements LtiException
{
    public function __construct(
        public readonly string $error,
        string $description,
    ) {
        parent::__construct($description);
    }

    public static function invalidRequest(string $description): self
    {
        return new self('invalid_request', $description);
    }

    public static function invalidClient(string $description): self
    {
        return new self('invalid_client', $description);
    }

    public static function unsupportedGrantType(): self
    {
        return new self('unsupported_grant_type', 'Only the grant "client_credentials" is supported.');
    }

    public static function invalidScope(string $description): self
    {
        return new self('invalid_scope', $description);
    }

    /**
     * The body of the error answer, as RFC 6749 writes it.
     *
     * @return array{error: string, error_description: string}
     */
    public function toArray(): array
    {
        return ['error' => $this->error, 'error_description' => $this->getMessage()];
    }
}
