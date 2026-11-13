<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use Phil\AccessLinks\Exception\InvalidToken;
use Random\Randomizer;

/**
 * The secret of a personal link: 32 bytes of a cryptographically secure generator, 256 bits, written in URL-safe
 * Base64 without padding, 43 characters. It names nothing — no recipient, no address, no campaign — and is stored
 * only as its keyed hash.
 */
final class LinkToken
{
    public const int BYTES = 32;

    public const int LENGTH = 43;

    private function __construct()
    {
    }

    /**
     * @return non-empty-string
     */
    public static function generate(Randomizer $randomizer): string
    {
        return sodium_bin2base64($randomizer->getBytes(self::BYTES), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * The token as it was issued, from the text of a link.
     *
     * @return non-empty-string
     *
     * @throws InvalidToken when the text is not 43 characters of URL-safe Base64 encoding 32 bytes
     */
    public static function parse(string $text): string
    {
        // 42 characters carry 252 bits; the last carries the 4 remaining bits and two zero bits.
        if (1 !== preg_match('/^[A-Za-z0-9_-]{42}[AEIMQUYcgkosw048]$/D', $text)) {
            throw InvalidToken::malformed();
        }

        return $text;
    }
}
