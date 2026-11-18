<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Support;

use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * RSA key pairs made by OpenSSL while the tests run, never kept in the repository: each is made once per size and name
 * for the run. A key of 4096 bits or more takes seconds to make, so it is also kept in the temporary directory, readable
 * by its owner only, for the other processes of the run — those of ParaTest and Infection.
 */
final class TestKeys
{
    /** @var array<string, array{string, string}> the private and public keys in PEM, by size and name */
    private static array $pairs = [];

    private function __construct()
    {
    }

    /**
     * @return array{string, string} the private key and the public key, in PEM
     */
    public static function rsa(int $bits, string $name = 'first'): array
    {
        return self::$pairs[$bits.'-'.$name] ??= $bits >= 4096 ? self::kept($bits, $name) : self::make($bits);
    }

    /**
     * @return array{string, string}
     */
    private static function kept(int $bits, string $name): array
    {
        $file = \sprintf('%s/phil-open-badges-tests/rsa-%d-%s.pem', sys_get_temp_dir(), $bits, $name);
        $private = is_file($file) ? file_get_contents($file) : false;
        $key = false === $private ? false : openssl_pkey_get_private($private);

        if (false === $key) {
            $pair = self::make($bits);
            [$private] = $pair;
            $directory = \dirname($file);

            if (!is_dir($directory) && !mkdir($directory, 0o700, true) && !is_dir($directory)) {
                throw new RuntimeException(\sprintf('The directory "%s" cannot be made.', $directory));
            }

            // Written aside then renamed, so that a process of the run never reads half a key.
            $partial = $file.'.'.bin2hex(random_bytes(8));
            file_put_contents($partial, $private);
            chmod($partial, 0o600);
            rename($partial, $file);

            return $pair;
        }

        return [$private, self::details($key)];
    }

    /**
     * @return array{string, string}
     */
    private static function make(int $bits): array
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);

        if (false === $key || !openssl_pkey_export($key, $private) || !\is_string($private)) {
            throw new RuntimeException('OpenSSL did not make an RSA key.');
        }

        return [$private, self::details($key)];
    }

    private static function details(OpenSSLAsymmetricKey $key): string
    {
        $details = openssl_pkey_get_details($key);

        if (!\is_array($details) || !\is_string($details['key'] ?? null)) {
            throw new RuntimeException('OpenSSL did not give the public key.');
        }

        return $details['key'];
    }
}
