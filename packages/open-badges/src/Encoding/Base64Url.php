<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Encoding;

use Phil\OpenBadges\Exception\InvalidCredential;
use SodiumException;

/**
 * The URL-safe base 64 of JSON Web Tokens and of multibase `u`, without padding, through libsodium's constant-time
 * codec.
 */
final class Base64Url
{
    private function __construct()
    {
    }

    public static function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws InvalidCredential
     */
    public static function decode(string $text): string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw InvalidCredential::because('A part of the token is not URL-safe base 64.');
        }
    }
}
