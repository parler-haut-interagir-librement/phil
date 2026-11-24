<?php

declare(strict_types=1);

namespace Phil\SamlSp\Xml;

use SodiumException;

/**
 * Base64 as SAML carries it — standard alphabet, padded, spaces and line breaks allowed in what is read —, in constant
 * time through sodium, since signatures, keys and assertions pass through it.
 */
final class Base64
{
    private function __construct()
    {
    }

    public static function encode(string $binary): string
    {
        return sodium_bin2base64($binary, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * The bytes of the text, null when it is not base64 or holds no byte.
     */
    public static function decode(string $text): ?string
    {
        try {
            $binary = sodium_base642bin($text, \SODIUM_BASE64_VARIANT_ORIGINAL, " \t\r\n");
        } catch (SodiumException) {
            return null;
        }

        return '' === $binary ? null : $binary;
    }
}
