<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Signature;

use SodiumException;

/**
 * Base64 without padding, in the URL alphabet, for signatures and nonces; the keys of the configuration are read in
 * either alphabet, with or without padding.
 */
final readonly class Base64Url
{
    public static function encode(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * The bytes, or null when the text is not base64 in the URL alphabet without padding, or encodes nothing.
     *
     * @return ?non-empty-string
     */
    public static function decode(string $text): ?string
    {
        try {
            $bytes = sodium_base642bin($text, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

            return '' === $bytes ? null : $bytes;
        } catch (SodiumException) {
            return null;
        }
    }

    /**
     * The bytes of a key as people paste it: either alphabet, padded or not, surrounding spaces ignored.
     *
     * @return ?non-empty-string
     */
    public static function decodeKey(string $text): ?string
    {
        $normalised = rtrim(strtr(trim($text), '+/', '-_'), '=');

        return '' === $normalised ? null : self::decode($normalised);
    }
}
