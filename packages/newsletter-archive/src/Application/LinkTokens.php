<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Application;

use SensitiveParameter;

/**
 * The opaque tokens of the links issues are shared by: 32 random bytes, in Base64 for addresses without padding, 43
 * characters. The archive keeps their SHA-256 alone, so that a copy of its table opens no link; a token of 256 random
 * bits needs no key nor slow hash to resist a search.
 */
final class LinkTokens
{
    public const int LENGTH = 43;

    private const string PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    /**
     * A new token.
     *
     * @return non-empty-string
     */
    public static function generate(): string
    {
        return sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Whether a text has the shape of a token, checked before it is hashed and looked up.
     */
    public static function isWellFormed(#[SensitiveParameter] string $token): bool
    {
        return 1 === preg_match(self::PATTERN, $token);
    }

    /**
     * The hash the archive keeps of a token: SHA-256 in lowercase hexadecimal.
     */
    public static function hash(#[SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }
}
