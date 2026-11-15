<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Oidc;

use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;

/**
 * Proof Key for Code Exchange (RFC 7636), with the S256 method only: `plain` gives no protection against a code
 * read on its way back. A verifier is 43 to 128 characters of the unreserved set, and its SHA-256, in base64url,
 * must be the challenge the authorization request carried.
 */
final class Pkce
{
    public const string CHALLENGE_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    public const string VERIFIER_PATTERN = '/^[A-Za-z0-9._~-]{43,128}$/D';

    private function __construct()
    {
    }

    public static function challengeOf(string $verifier): string
    {
        return Base64Url::encode(hash('sha256', $verifier, true));
    }

    public static function verify(string $verifier, string $challenge): bool
    {
        return 1 === preg_match(self::VERIFIER_PATTERN, $verifier) && hash_equals($challenge, self::challengeOf($verifier));
    }
}
