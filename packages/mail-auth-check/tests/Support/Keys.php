<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Support;

/**
 * Public keys built byte by byte, of an exact size: only their lengths matter to the package.
 */
final class Keys
{
    public static function der(int $tag, string $content): string
    {
        $length = \strlen($content);

        if ($length < 0x80) {
            return pack('CC', $tag, $length).$content;
        }

        if ($length < 0x100) {
            return pack('CCC', $tag, 0x81, $length).$content;
        }

        return pack('CCn', $tag, 0x82, $length).$content;
    }

    /**
     * An RSAPublicKey whose modulus has exactly this many bits.
     */
    public static function rsaPublicKey(int $bits): string
    {
        $bytes = intdiv($bits + 7, 8);
        $top = 1 << (($bits - 1) % 8);
        $modulus = "\x00".pack('C', $top).str_repeat("\xA5", $bytes - 1);

        return self::der(0x30, self::der(0x02, $modulus).self::der(0x02, "\x01\x00\x01"));
    }

    /**
     * A SubjectPublicKeyInfo of an RSA key, as DKIM publishes it.
     */
    public static function rsaSpki(int $bits): string
    {
        $algorithm = self::der(0x30, "\x06\x09\x2A\x86\x48\x86\xF7\x0D\x01\x01\x01\x05\x00");

        return self::der(0x30, $algorithm.self::der(0x03, "\x00".self::rsaPublicKey($bits)));
    }

    public static function base64(string $der): string
    {
        return sodium_bin2base64($der, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public static function dkimRsa(int $bits): string
    {
        return 'v=DKIM1; k=rsa; p='.self::base64(self::rsaSpki($bits));
    }

    public static function dkimEd25519(): string
    {
        return 'v=DKIM1; k=ed25519; p='.self::base64(str_repeat("\x11", 32));
    }
}
