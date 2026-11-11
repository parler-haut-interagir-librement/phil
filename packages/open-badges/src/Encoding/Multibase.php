<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Encoding;

use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * The two multibase encodings of Data Integrity and of the Bitstring Status List: `z`, base 58 of Bitcoin's alphabet,
 * and `u`, URL-safe base 64 without padding.
 */
final class Multibase
{
    private function __construct()
    {
    }

    public static function base58(string $bytes): string
    {
        return 'z'.Base58Btc::encode($bytes);
    }

    public static function base64Url(string $bytes): string
    {
        return 'u'.Base64Url::encode($bytes);
    }

    /**
     * The bytes of a `z` or `u` multibase text.
     *
     * @throws InvalidCredential
     */
    public static function decode(string $text): string
    {
        return match ($text[0] ?? '') {
            'z' => Base58Btc::decode(substr($text, 1)),
            'u' => Base64Url::decode(substr($text, 1)),
            default => throw InvalidCredential::because('A multibase text starts with "z" (base 58) or "u" (base 64 URL).'),
        };
    }
}
