<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Domain\Token;

/**
 * The 62 letters and digits of a token: random strings drawn from them without bias, and the fixed-width text of a
 * checksum.
 *
 * @internal
 */
final class Base62
{
    public const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    private function __construct()
    {
    }

    /**
     * A string of random characters of the alphabet, each drawn by `random_int()` from the system's secure source,
     * every character as likely as any other.
     *
     * @param positive-int $length
     */
    public static function random(int $length): string
    {
        $text = '';

        for ($position = 0; $position < $length; ++$position) {
            $text .= self::ALPHABET[random_int(0, 61)];
        }

        return $text;
    }

    /**
     * A non-negative integer in exactly `$width` characters, padded with leading zeros.
     *
     * @param non-negative-int $value
     * @param positive-int     $width
     */
    public static function fixed(int $value, int $width): string
    {
        $text = '';

        for ($position = 0; $position < $width; ++$position) {
            $text = self::ALPHABET[$value % 62].$text;
            $value = intdiv($value, 62);
        }

        return $text;
    }

    /**
     * Whether every character of the string is a letter or a digit of the alphabet.
     */
    public static function isEncoded(string $text): bool
    {
        return \strlen($text) === strspn($text, self::ALPHABET);
    }
}
