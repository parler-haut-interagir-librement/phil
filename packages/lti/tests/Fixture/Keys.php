<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Fixture;

use Phil\Lti\Key\RsaPrivateKey;
use RuntimeException;

/**
 * Keys of the tests, generated on the first run and kept in the temporary directory, so that no private key is
 * committed and the processes of a run share them: a key is written to a file of its own and renamed into place.
 */
final class Keys
{
    /** @var array<string, string> */
    private static array $pems = [];

    public static function platform(): RsaPrivateKey
    {
        return RsaPrivateKey::fromPem(self::pem('platform'), 'platform-1');
    }

    public static function platformNext(): RsaPrivateKey
    {
        return RsaPrivateKey::fromPem(self::pem('platform-next'), 'platform-2');
    }

    public static function tool(): RsaPrivateKey
    {
        return RsaPrivateKey::fromPem(self::pem('tool'), 'tool-1');
    }

    public static function pem(string $name): string
    {
        if (isset(self::$pems[$name])) {
            return self::$pems[$name];
        }

        $directory = sys_get_temp_dir().'/phil-lti-test-keys';
        $file = $directory.'/'.$name.'.pem';

        if (!is_file($file)) {
            if (!is_dir($directory) && !@mkdir($directory, 0o700, true) && !is_dir($directory)) {
                throw new RuntimeException('The directory of the test keys cannot be made.');
            }

            $options = match ($name) {
                'short' => ['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 1024],
                'ec' => ['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'],
                default => ['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048],
            };
            $key = openssl_pkey_new($options);

            if (false === $key || !openssl_pkey_export($key, $pem) || !\is_string($pem)) {
                throw new RuntimeException('OpenSSL cannot generate the test key.');
            }

            $temporary = $file.'.'.bin2hex(random_bytes(6));
            file_put_contents($temporary, $pem);
            rename($temporary, $file);
        }

        return self::$pems[$name] = (string) file_get_contents($file);
    }

    public static function publicPem(string $name): string
    {
        $key = openssl_pkey_get_private(self::pem($name));
        $details = false === $key ? false : openssl_pkey_get_details($key);

        return \is_array($details) && \is_string($details['key'] ?? null) ? $details['key'] : throw new RuntimeException('No public key.');
    }
}
