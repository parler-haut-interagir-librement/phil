<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Encoding;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use SensitiveParameter;

/**
 * Base32 (RFC 4648, section 6), the alphabet authenticator applications read TOTP secrets in. The encoder writes no
 * padding; the decoder accepts lower case, spaces and padding, which people and applications add.
 */
final class Base32
{
    public const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private function __construct()
    {
    }

    public static function encode(#[SensitiveParameter] string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(\ord($byte[0])), 8, '0', \STR_PAD_LEFT);
        }

        $text = '';

        foreach (str_split($bits, 5) as $group) {
            $text .= self::ALPHABET[(int) bindec(str_pad($group, 5, '0'))];
        }

        return $text;
    }

    /**
     * @throws MalformedInput when the text holds a character outside the alphabet
     */
    public static function decode(#[SensitiveParameter] string $text): string
    {
        $text = strtoupper(rtrim(str_replace(' ', '', $text), '='));
        $bits = '';

        foreach (str_split($text) as $character) {
            $value = strpos(self::ALPHABET, $character);

            if (false === $value) {
                throw MalformedInput::because('The text is not base32.');
            }

            $bits .= str_pad(decbin($value), 5, '0', \STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split($bits, 8) as $group) {
            if (8 === \strlen($group)) {
                $bytes .= \chr((int) bindec($group) & 0xFF);
            }
        }

        return $bytes;
    }
}
