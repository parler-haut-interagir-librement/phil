<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A security event token refused, with the error code RFC 8935 answers it with: `invalid_request` for a token that is
 * not a security event token, `invalid_key` and `authentication_failed` for a signature, `invalid_issuer` and
 * `invalid_audience` for a token meant for someone else. The description names no value of the token.
 */
final class InvalidSecurityEventToken extends InvalidArgumentException
{
    public const string INVALID_REQUEST = 'invalid_request';

    public const string INVALID_KEY = 'invalid_key';

    public const string INVALID_ISSUER = 'invalid_issuer';

    public const string INVALID_AUDIENCE = 'invalid_audience';

    public const string AUTHENTICATION_FAILED = 'authentication_failed';

    public function __construct(
        public readonly string $error,
        string $description,
    ) {
        parent::__construct($description);
    }

    public static function malformed(string $description): self
    {
        return new self(self::INVALID_REQUEST, $description);
    }

    public static function key(string $description): self
    {
        return new self(self::INVALID_KEY, $description);
    }

    public static function issuer(): self
    {
        return new self(self::INVALID_ISSUER, 'The token was not issued by the transmitter.');
    }

    public static function audience(): self
    {
        return new self(self::INVALID_AUDIENCE, 'The token is not meant for this receiver.');
    }

    public static function signature(): self
    {
        return new self(self::AUTHENTICATION_FAILED, 'The signature of the token does not hold.');
    }
}
