<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use RuntimeException;

/**
 * The test keys of tests/Fixtures/keys: RSA keys and self-signed certificates made for these tests only, documented in
 * doc/testing.md. `entra` and `okta` sign as the two identity providers, `sp` signs and decrypts as the service
 * provider, `attacker` signs what must be refused, `weak` is 1024 bits, `expired` expired in 2021.
 */
final class Keys
{
    public const string DIRECTORY = __DIR__.'/../Fixtures/keys';

    public static function pem(string $file): string
    {
        $pem = file_get_contents(self::DIRECTORY.'/'.$file);

        if (false === $pem) {
            throw new RuntimeException(\sprintf('The test key "%s" is missing.', $file));
        }

        return $pem;
    }

    /**
     * The text of a file of tests/Fixtures/responses.
     */
    public static function fixture(string $name): string
    {
        $xml = file_get_contents(__DIR__.'/../Fixtures/responses/'.$name);

        if (false === $xml) {
            throw new RuntimeException(\sprintf('The fixture "%s" is missing.', $name));
        }

        return $xml;
    }

    public static function certificate(string $name): Certificate
    {
        return Certificate::fromPem(self::pem($name.'.crt'));
    }

    public static function privateKey(string $name): PrivateKey
    {
        return PrivateKey::fromPem(self::pem($name.'.key'));
    }

    public static function pair(string $name): KeyPair
    {
        return new KeyPair(self::privateKey($name), self::certificate($name));
    }
}
