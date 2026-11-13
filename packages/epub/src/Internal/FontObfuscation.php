<?php

declare(strict_types=1);

namespace Phil\Epub\Internal;

/**
 * The IDPF font obfuscation algorithm of EPUB 3: the first 1040 bytes of the font combined by exclusive or with the
 * SHA-1 of the unique identifier stripped of its spaces, tabs, carriage returns and line feeds. Applied twice, it
 * gives the font back.
 *
 * @internal
 */
final class FontObfuscation
{
    private const int LENGTH = 1040;

    /**
     * The 20 bytes of the key repeated over the 1040 bytes.
     */
    private const int REPEAT = 52;

    public static function apply(string $font, string $uniqueIdentifier): string
    {
        $key = hash('sha1', str_replace([' ', "\t", "\r", "\n"], '', $uniqueIdentifier), true);

        return ($font ^ str_repeat($key, self::REPEAT)).substr($font, self::LENGTH);
    }
}
