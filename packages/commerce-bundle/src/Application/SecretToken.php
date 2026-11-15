<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

/**
 * The secret of a link — a shared wish list, a payment link —: 192 random bits written as 32 characters of URL-safe
 * base 64, shown once, of which only the hexadecimal SHA-256 is stored.
 */
final class SecretToken
{
    private function __construct()
    {
    }

    /**
     * @return array{string, string} the token to show once, and the hash to store
     */
    public static function create(): array
    {
        $token = sodium_bin2base64(random_bytes(24), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return [$token, hash('sha256', $token)];
    }

    /**
     * The hash of a token as a visitor gives it, or `null` for a string that cannot be one: no lookup is made for it.
     */
    public static function hash(string $token): ?string
    {
        return 1 === preg_match('/^[A-Za-z0-9_-]{32}$/D', $token) ? hash('sha256', $token) : null;
    }
}
