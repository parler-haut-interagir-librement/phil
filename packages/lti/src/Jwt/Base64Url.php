<?php

declare(strict_types=1);

namespace Phil\Lti\Jwt;

use Phil\Lti\Exception\InvalidToken;
use SodiumException;

/**
 * URL-safe base 64 without padding, as RFC 7515 writes the parts of a token; strict when it reads.
 */
final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return '' === $bytes ? '' : sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws InvalidToken when the text holds anything but the 64 letters of the alphabet
     */
    public static function decode(string $text): string
    {
        if ('' === $text) {
            return '';
        }

        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException $sodiumException) {
            throw InvalidToken::because('A part of the token is not in URL-safe base 64.', $sodiumException);
        }
    }
}
