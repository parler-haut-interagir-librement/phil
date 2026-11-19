<?php

declare(strict_types=1);

namespace Phil\MimeReader\Internal;

/**
 * Base64 decoded leniently, as mail needs it, with the timing-safe decoder of sodium: whatever is not of the alphabet
 * is dropped, a last group of two or three characters is decoded with its unused bits cleared, and a last lone
 * character is dropped.
 *
 * @internal
 */
final class Base64
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    public static function decode(string $text): string
    {
        $text = (string) preg_replace('/[^A-Za-z0-9+\/]+/', '', $text);

        return self::groups($text, \strlen($text) % 4);
    }

    /**
     * Characters of the alphabet only: whole groups, then the last incomplete one.
     */
    public static function groups(string $alphabet, int $tail): string
    {
        $bytes = sodium_base642bin(substr($alphabet, 0, \strlen($alphabet) - $tail), \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);

        if ($tail < 2) {
            return $bytes;
        }

        $last = (int) strpos(self::ALPHABET, $alphabet[-1]);
        $kept = 2 === $tail ? 0x30 : 0x3C;

        return $bytes.sodium_base642bin(substr($alphabet, -$tail, $tail - 1).self::ALPHABET[$last & $kept], \SODIUM_BASE64_VARIANT_ORIGINAL_NO_PADDING);
    }
}
