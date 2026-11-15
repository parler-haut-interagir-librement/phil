<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Encoding;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use SensitiveParameter;
use SodiumException;

/**
 * Base64url without padding (RFC 4648, section 5), as JSON Web Tokens and WebAuthn write bytes, through libsodium:
 * its decoder runs in constant time and refuses every character outside the alphabet, padding included.
 */
final class Base64Url
{
    private function __construct()
    {
    }

    public static function encode(#[SensitiveParameter] string $bytes): string
    {
        return '' === $bytes ? '' : sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * @throws MalformedInput when the text holds a character outside the alphabet, padding or an impossible length
     */
    public static function decode(#[SensitiveParameter] string $text): string
    {
        try {
            return sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw MalformedInput::because('The text is not base64url without padding.');
        }
    }
}
