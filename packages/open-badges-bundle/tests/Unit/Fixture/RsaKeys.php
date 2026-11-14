<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit\Fixture;

use Phil\OpenBadges\Key\RsaSigner;
use RuntimeException;

/**
 * RSA signers generated once per size for the run, since a key takes OpenSSL a while to make.
 */
final class RsaKeys
{
    /** @var array<int, string> the private keys in PEM, by size */
    private static array $pems = [];

    private function __construct()
    {
    }

    public static function signer(int $bits = 2048): RsaSigner
    {
        if (!isset(self::$pems[$bits])) {
            $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);

            if (false === $key || !openssl_pkey_export($key, $pem) || !\is_string($pem)) {
                throw new RuntimeException('OpenSSL did not make an RSA key.');
            }

            self::$pems[$bits] = $pem;
        }

        return RsaSigner::fromPem(self::$pems[$bits]);
    }
}
