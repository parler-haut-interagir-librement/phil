<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Encoding;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;

/**
 * The few DER structures (X.690) the public keys of passkeys need: a public key written as a SubjectPublicKeyInfo
 * for OpenSSL.
 */
final class Der
{
    private const string RSA_ALGORITHM = "\x30\x0D\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00";

    private const string P256_ALGORITHM = "\x30\x13\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";

    private function __construct()
    {
    }

    /**
     * The PEM of an RSA public key, from its modulus and exponent as unsigned big-endian bytes.
     *
     * @return non-empty-string
     */
    public static function rsaPublicKey(string $modulus, string $exponent): string
    {
        $key = self::sequence(self::integer($modulus).self::integer($exponent));

        return self::pem(self::sequence(self::RSA_ALGORITHM.self::bitString($key)));
    }

    /**
     * The PEM of a P-256 public key, from its coordinates of 32 bytes each.
     *
     * @return non-empty-string
     */
    public static function p256PublicKey(string $x, string $y): string
    {
        if (32 !== \strlen($x) || 32 !== \strlen($y)) {
            throw MalformedInput::because('A P-256 coordinate holds 32 bytes.');
        }

        return self::pem(self::sequence(self::P256_ALGORITHM.self::bitString("\x04".$x.$y)));
    }

    /**
     * @return non-empty-string
     */
    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(sodium_bin2base64($der, \SODIUM_BASE64_VARIANT_ORIGINAL), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function sequence(string $content): string
    {
        return "\x30".self::length(\strlen($content)).$content;
    }

    private static function bitString(string $content): string
    {
        return "\x03".self::length(\strlen($content) + 1)."\x00".$content;
    }

    private static function integer(string $unsigned): string
    {
        $value = ltrim($unsigned, "\x00");

        if ('' === $value || \ord($value[0]) > 0x7F) {
            $value = "\x00".$value;
        }

        return "\x02".self::length(\strlen($value)).$value;
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return \chr($length & 0x7F);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return \chr(0x80 | (\strlen($bytes) & 0x7F)).$bytes;
    }
}
