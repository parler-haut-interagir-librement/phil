<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use RuntimeException;

/**
 * A private key of the tests, generated on the first run and kept in the temporary directory, so that none is committed.
 */
final class TestKeys
{
    private static ?string $pem = null;

    public static function pem(): string
    {
        if (null !== self::$pem) {
            return self::$pem;
        }

        $file = sys_get_temp_dir().'/phil-lti-bundle-test-key.pem';

        if (!is_file($file)) {
            $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);

            if (false === $key || !openssl_pkey_export($key, $pem) || !\is_string($pem)) {
                throw new RuntimeException('OpenSSL cannot generate the test key.');
            }

            $temporary = $file.'.'.sodium_bin2hex(random_bytes(6));
            file_put_contents($temporary, $pem);
            rename($temporary, $file);
        }

        return self::$pem = (string) file_get_contents($file);
    }
}
