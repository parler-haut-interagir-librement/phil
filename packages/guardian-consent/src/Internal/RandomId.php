<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Internal;

use Random\Randomizer;

/**
 * Random bytes written in URL-safe Base64 without padding, and the check of such a text.
 *
 * @internal
 */
final class RandomId
{
    private function __construct()
    {
    }

    /**
     * @param int<1, max> $bytes
     *
     * @return non-empty-string
     */
    public static function generate(Randomizer $randomizer, int $bytes): string
    {
        return sodium_bin2base64($randomizer->getBytes($bytes), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Whether the text is exactly what `generate()` writes for 16 or 32 bytes: its length, its alphabet, and no bit set
     * past the last byte, which the last character tells.
     *
     * @param 16|32 $bytes
     */
    public static function isValid(string $text, int $bytes): bool
    {
        // 16 bytes are 21 characters of 6 bits and one of 2 bits and four zero bits; 32 bytes, 42 characters and one of
        // 4 bits and two zero bits.
        $pattern = 16 === $bytes ? '/^[A-Za-z0-9_-]{21}[AQgw]$/D' : '/^[A-Za-z0-9_-]{42}[AEIMQUYcgkosw048]$/D';

        return 1 === preg_match($pattern, $text);
    }
}
