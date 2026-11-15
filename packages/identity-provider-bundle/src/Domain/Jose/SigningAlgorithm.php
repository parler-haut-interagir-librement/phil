<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Jose;

/**
 * The signature algorithms of the provider's tokens (RFC 7518, RFC 8037) and of passkeys (their COSE identifiers,
 * RFC 9053). `none` and the HMAC algorithms are not among them: a token signed with a shared secret could be forged
 * by any client that holds it.
 */
enum SigningAlgorithm: string
{
    case RS256 = 'RS256';
    case ES256 = 'ES256';
    case EdDSA = 'EdDSA';

    public static function fromCose(int $identifier): ?self
    {
        return match ($identifier) {
            -257 => self::RS256,
            -7 => self::ES256,
            -8 => self::EdDSA,
            default => null,
        };
    }

    public function cose(): int
    {
        return match ($this) {
            self::RS256 => -257,
            self::ES256 => -7,
            self::EdDSA => -8,
        };
    }

    public function keyType(): string
    {
        return match ($this) {
            self::RS256 => 'RSA',
            self::ES256 => 'EC',
            self::EdDSA => 'OKP',
        };
    }
}
