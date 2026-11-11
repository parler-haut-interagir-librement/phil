<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Encoding;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The base 58 of Bitcoin's alphabet, which multibase names `z`: the encoding of Data Integrity proof values and of
 * Multikey public keys. Each leading zero byte is a leading `1`.
 *
 * Its arithmetic is quadratic in the length, so a text longer than `MAX_LENGTH` characters is refused before it is
 * decoded: a proof value or a key is under a hundred.
 */
final class Base58Btc
{
    public const string ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public const int MAX_LENGTH = 512;

    private function __construct()
    {
    }

    public static function encode(string $bytes): string
    {
        $zeros = \strlen($bytes) - \strlen(ltrim($bytes, "\0"));
        $digits = [];

        foreach (str_split($bytes) as $byte) {
            $carry = \ord($byte[0]);

            foreach ($digits as $index => $digit) {
                $carry += $digit << 8;
                $digits[$index] = $carry % 58;
                $carry = intdiv($carry, 58);
            }

            for (; $carry > 0; $carry = intdiv($carry, 58)) {
                $digits[] = $carry % 58;
            }
        }

        $text = str_repeat('1', $zeros);

        foreach (array_reverse($digits) as $digit) {
            $text .= self::ALPHABET[$digit];
        }

        return $text;
    }

    /**
     * @throws InvalidCredential
     */
    public static function decode(string $text): string
    {
        if (\strlen($text) > self::MAX_LENGTH || \strlen($text) !== strspn($text, self::ALPHABET)) {
            throw InvalidCredential::because(\sprintf('A base 58 text has at most %d characters of the Bitcoin alphabet.', self::MAX_LENGTH));
        }

        $zeros = \strlen($text) - \strlen(ltrim($text, '1'));
        $bytes = [];

        $values = array_flip(str_split(self::ALPHABET));

        foreach (str_split($text) as $character) {
            $carry = $values[$character];

            foreach ($bytes as $index => $byte) {
                $carry += $byte * 58;
                $bytes[$index] = $carry & 0xFF;
                $carry >>= 8;
            }

            // What is left is below 59: one more byte at most.
            if ($carry > 0) {
                $bytes[] = $carry;
            }
        }

        return str_repeat("\0", $zeros).pack('C*', ...array_reverse($bytes));
    }
}
