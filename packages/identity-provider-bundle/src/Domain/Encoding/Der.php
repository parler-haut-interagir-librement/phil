<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Domain\Encoding;

use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;

/**
 * The few DER structures (X.690) the keys need: a public key of a passkey written as a SubjectPublicKeyInfo for
 * OpenSSL, a certificate written as PEM, and an ECDSA signature of OpenSSL read back as the two numbers JOSE writes.
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
     * The PEM of a certificate from its DER, as the `x5c` of an attestation carries it.
     *
     * @return non-empty-string
     */
    public static function certificate(string $der): string
    {
        return self::pem($der, 'CERTIFICATE');
    }

    /**
     * The `r` and `s` of an ECDSA signature in DER, `SEQUENCE { INTEGER r, INTEGER s }`, each as unsigned big-endian
     * bytes left-padded to the length of the curve: the signature of JWS (RFC 7518, section 3.4).
     *
     * @throws MalformedInput when the bytes are not such a sequence, or a number is longer than the curve
     */
    public static function ecdsaSignature(string $der, int $length): string
    {
        $size = \strlen($der);

        if ($size < 8 || "\x30" !== $der[0] || \ord($der[1]) !== $size - 2) {
            throw MalformedInput::because('An ECDSA signature is a DER sequence of two integers.');
        }

        $offset = 2;
        $parts = '';

        foreach ([0, 1] as $part) {
            $partLength = $offset + 2 <= $size && "\x02" === $der[$offset] ? \ord($der[$offset + 1]) : -1;
            $number = ltrim(substr($der, $offset + 2, max(0, $partLength)), "\x00");

            if ($partLength < 1 || $partLength > 0x7F || $offset + 2 + $partLength > $size || \strlen($number) > $length) {
                throw MalformedInput::because(\sprintf('Integer %d of an ECDSA signature is out of bounds.', $part + 1));
            }

            $parts .= str_pad($number, $length, "\x00", \STR_PAD_LEFT);
            $offset += 2 + $partLength;
        }

        if ($offset !== $size) {
            throw MalformedInput::because('An ECDSA signature holds bytes after its two integers.');
        }

        return $parts;
    }

    /**
     * @return non-empty-string
     */
    private static function pem(string $der, string $label = 'PUBLIC KEY'): string
    {
        return "-----BEGIN {$label}-----\n".chunk_split(sodium_bin2base64($der, \SODIUM_BASE64_VARIANT_ORIGINAL), 64, "\n")."-----END {$label}-----\n";
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
