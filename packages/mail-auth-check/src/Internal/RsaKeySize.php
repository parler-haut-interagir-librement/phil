<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Phil\MailAuthCheck\Exception\InvalidRecord;

/**
 * Reads the size of an RSA public key from its DER form: a SubjectPublicKeyInfo, as DKIM publishes it, or a bare
 * RSAPublicKey. Only the lengths are read; the key is never used.
 *
 * @internal
 */
final class RsaKeySize
{
    private const string RSA_ENCRYPTION = "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01";

    /**
     * @throws InvalidRecord
     */
    public static function bits(string $der): int
    {
        $offset = 0;
        $outer = self::element($der, $offset, 0x30);

        if ('' !== $outer && "\x30" === $outer[0]) {
            $inner = 0;
            $algorithm = self::element($outer, $inner, 0x30);

            if (!str_starts_with($algorithm, self::RSA_ENCRYPTION)) {
                throw self::invalid('it is not an RSA key');
            }

            $bitString = self::element($outer, $inner, 0x03);

            if (!str_starts_with($bitString, "\x00")) {
                throw self::invalid('its key bits are not whole bytes');
            }

            $offset = 0;
            $outer = self::element(substr($bitString, 1), $offset, 0x30);
        }

        $inner = 0;
        $modulus = ltrim(self::element($outer, $inner, 0x02), "\x00");

        if ('' === $modulus) {
            throw self::invalid('its modulus is zero');
        }

        return \strlen($modulus) * 8 - 8 + \strlen(decbin(\ord($modulus[0])));
    }

    /**
     * The content of the DER element at the offset, which moves past it.
     */
    private static function element(string $der, int &$offset, int $tag): string
    {
        if (!isset($der[$offset + 1]) || \ord($der[$offset]) !== $tag) {
            throw self::invalid('its DER structure is not a public key');
        }

        $length = \ord($der[$offset + 1]);
        $offset += 2;

        if ($length > 0x80) {
            $bytes = $length - 0x80;

            if ($bytes > 2 || \strlen($der) < $offset + $bytes) {
                throw self::invalid('its DER lengths are out of range');
            }

            $length = 0;

            for ($end = $offset + $bytes; $offset < $end; ++$offset) {
                $length = $length * 256 + \ord($der[$offset]);
            }
        } elseif (0x80 === $length) {
            throw self::invalid('its DER lengths are out of range');
        }

        if (\strlen($der) < $offset + $length) {
            throw self::invalid('its DER structure is cut short');
        }

        $content = substr($der, $offset, $length);
        $offset += $length;

        return $content;
    }

    private static function invalid(string $reason): InvalidRecord
    {
        return InvalidRecord::because(\sprintf('the public key cannot be read: %s.', $reason));
    }
}
