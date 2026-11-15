<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Key;

use Jose\Component\Signature\Algorithm\RS256;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Base64Url;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Lti\Tests\Fixture\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RsaPublicKey::class)]
#[CoversClass(RsaPrivateKey::class)]
final class RsaKeysTest extends TestCase
{
    public function testAKeySignsAndItsPublicHalfVerifies(): void
    {
        $key = Keys::platform();
        $signature = $this->sign($key, 'data');

        self::assertSame('platform-1', $key->keyId());
        self::assertSame(256, \strlen($signature));
        self::assertTrue($this->verify($key->publicKey, 'data', $signature));
        self::assertFalse($this->verify($key->publicKey, 'other', $signature));
        self::assertFalse($this->verify(Keys::tool()->publicKey, 'data', $signature));
    }

    public function testThePrivateJsonWebKeyRoundTripsWithoutShowingItsSecret(): void
    {
        $key = Keys::platform();
        $jwk = $key->toJwk();

        self::assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e', 'd', 'p', 'q', 'dp', 'dq', 'qi'], array_keys($jwk));
        self::assertSame('platform-1', $key->jwk()->get('kid'));

        $read = RsaPrivateKey::fromJwk($jwk);
        self::assertSame('platform-1', $read->keyId());
        self::assertSame('other', RsaPrivateKey::fromJwk($jwk, 'other')->keyId());
        self::assertSame($key->publicKey->thumbprint(), RsaPrivateKey::fromJwk(array_diff_key($jwk, ['kid' => true]))->keyId());
        self::assertSame($key->publicKey->thumbprint(), RsaPrivateKey::fromJwk(['kid' => 3] + $jwk)->keyId());
        self::assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e', 'd'], array_keys(RsaPrivateKey::fromJwk(array_intersect_key($jwk, array_flip(['kty', 'kid', 'n', 'e', 'd'])))->toJwk()));
        self::assertTrue($this->verify($key->publicKey, 'data', $this->sign($read, 'data')));

        $dump = print_r($key, true);
        self::assertStringContainsString('platform-1', $dump);
        self::assertStringNotContainsString($jwk['d'], $dump);
        self::assertSame(['keyId', 'publicKey'], array_keys($key->__debugInfo()));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badPrivateJwks(): iterable
    {
        $jwk = Keys::platform()->toJwk();

        yield 'public only' => [array_diff_key($jwk, ['d' => true]), 'The key is not an RSA private key.'];
        yield 'empty exponent' => [['d' => ''] + $jwk, 'The key is not an RSA private key.'];
        yield 'numeric exponent' => [['d' => 3] + $jwk, 'The key is not an RSA private key.'];
        yield 'elliptic' => [['kty' => 'EC'] + $jwk, 'The key is not an RSA private key.'];
        yield 'numeric prime' => [['q' => 3] + $jwk, 'The member "q" of an RSA private key is a string.'];
        yield 'short' => [['n' => Base64Url::encode("\xff".str_repeat("\x01", 254))] + $jwk, 'The modulus of an RSA key has 2048 to 8192 bits, not 2040.'];
    }

    /**
     * @param array<string, mixed> $jwk
     */
    #[DataProvider('badPrivateJwks')]
    public function testAMalformedPrivateJsonWebKeyIsRefused(array $jwk, string $message): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains($message);

        RsaPrivateKey::fromJwk($jwk);
    }

    public function testTheJsonWebKeyRoundTrips(): void
    {
        $key = Keys::platform();
        $jwk = $key->publicKey->toJwk();

        self::assertSame(['kty', 'alg', 'use', 'kid', 'n', 'e'], array_keys($jwk));
        self::assertSame(['RSA', 'RS256', 'sig', 'platform-1'], [$jwk['kty'], $jwk['alg'], $jwk['use'], $jwk['kid'] ?? null]);
        self::assertSame('AQAB', $jwk['e']);
        self::assertSame(256, \strlen(Base64Url::decode($jwk['n'])));

        $read = RsaPublicKey::fromJwk($jwk);
        self::assertSame('platform-1', $read->keyId);
        self::assertSame($jwk, $read->toJwk());
        self::assertTrue($this->verify($read, 'data', $this->sign($key, 'data')));
        self::assertSame($read->toJwk(), RsaPublicKey::fromJwk(['d' => 'secret'] + $jwk)->toJwk());
    }

    public function testAKeyWithoutIdentifierIsNamedByItsThumbprint(): void
    {
        $key = RsaPrivateKey::fromPem(Keys::pem('tool'));
        $public = RsaPublicKey::fromPem(Keys::publicPem('tool'));

        self::assertNull($public->keyId);
        self::assertArrayNotHasKey('kid', $public->toJwk());
        self::assertSame($public->thumbprint(), $key->keyId());
        self::assertSame(43, \strlen($key->keyId()));
        self::assertSame($key->keyId(), RsaPrivateKey::fromPem(Keys::pem('tool'), '')->keyId());
        self::assertNull(RsaPublicKey::fromJwk(['kid' => ''] + $public->toJwk())->keyId);
    }

    public function testTheThumbprintFollowsRfc7638(): void
    {
        $public = RsaPublicKey::fromPem(Keys::publicPem('tool'));
        $jwk = $public->toJwk();
        $expected = Base64Url::encode(hash('sha256', '{"e":"'.$jwk['e'].'","kty":"RSA","n":"'.$jwk['n'].'"}', true));

        self::assertSame($expected, $public->thumbprint());
    }

    public function testAGeneratedKeyHasTheBitsAskedWithinTheBounds(): void
    {
        $key = RsaPrivateKey::generate(1024, 'k');
        $again = RsaPrivateKey::fromJson($key->toJson(), 'k');

        self::assertSame('k', $key->keyId());
        self::assertSame(256, \strlen(Base64Url::decode($key->publicKey->toJwk()['n'])));
        self::assertTrue($this->verify($again->publicKey, 'x', $this->sign($key, 'x')));
        self::assertSame($key->toJwk(), $again->toJwk());
        self::assertSame(RsaPrivateKey::DEFAULT_BITS, 2048);
    }

    public function testAnEncryptedKeyOpensWithItsPassphrase(): void
    {
        $private = openssl_pkey_get_private(Keys::pem('tool'));
        self::assertNotFalse($private);
        openssl_pkey_export($private, $encrypted, 'secret');
        self::assertIsString($encrypted);

        self::assertSame('t', RsaPrivateKey::fromPem($encrypted, 't', 'secret')->keyId());

        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains('The private key is not a key in PEM, or its passphrase is wrong.');
        RsaPrivateKey::fromPem($encrypted, 't', 'wrong');
    }

    public function testAShortOrNonRsaKeyIsRefused(): void
    {
        foreach (['short' => 'The modulus of an RSA key has 2048 to 8192 bits, not 1024.', 'ec' => ['The key is not an RSA private key.', 'The key is not an RSA key.']] as $name => $messages) {
            [$privateMessage, $message] = \is_array($messages) ? $messages : [$messages, $messages];

            try {
                RsaPrivateKey::fromPem(Keys::pem($name));
                self::fail('The key was read.');
            } catch (InvalidToken $invalidToken) {
                self::assertSame($privateMessage, $invalidToken->getMessage());
            }

            try {
                RsaPublicKey::fromPem(Keys::publicPem($name));
                self::fail('The key was read.');
            } catch (InvalidToken $invalidToken) {
                self::assertSame($message, $invalidToken->getMessage());
            }
        }
    }

    public function testTextThatIsNoKeyIsRefused(): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains('The public key is not a key in PEM.');

        RsaPublicKey::fromPem('nope');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badJwks(): iterable
    {
        $jwk = Keys::platform()->publicKey->toJwk();

        yield 'no type' => [['n' => $jwk['n'], 'e' => $jwk['e']], 'An RSA JSON Web Key has the type "RSA", a modulus "n" and an exponent "e".'];
        yield 'elliptic' => [['kty' => 'EC'] + $jwk, 'An RSA JSON Web Key has the type "RSA", a modulus "n" and an exponent "e".'];
        yield 'no modulus' => [['kty' => 'RSA', 'e' => 'AQAB'], 'An RSA JSON Web Key has the type "RSA", a modulus "n" and an exponent "e".'];
        yield 'numeric exponent' => [['kty' => 'RSA', 'n' => $jwk['n'], 'e' => 65537], 'An RSA JSON Web Key has the type "RSA", a modulus "n" and an exponent "e".'];
        yield 'other algorithm' => [['alg' => 'RS512'] + $jwk, 'An RSA JSON Web Key of LTI verifies RS256 signatures only.'];
        yield 'encryption' => [['use' => 'enc'] + $jwk, 'An RSA JSON Web Key of LTI is used for signatures.'];
        yield 'numeric kid' => [['kid' => 3] + $jwk, 'The "kid" of a JSON Web Key is a string.'];
        yield 'short modulus' => [['n' => Base64Url::encode("\xff".str_repeat("\x01", 254))] + $jwk, 'The modulus of an RSA key has 2048 to 8192 bits, not 2040.'];
        yield 'long modulus' => [['n' => Base64Url::encode("\x01".str_repeat("\x01", 1024))] + $jwk, 'The modulus of an RSA key has 2048 to 8192 bits, not 8193.'];
        yield 'empty modulus' => [['n' => Base64Url::encode("\x00")] + $jwk, 'The modulus of an RSA key has 2048 to 8192 bits, not 0.'];
        yield 'even exponent' => [['e' => Base64Url::encode("\x01\x00\x00")] + $jwk, 'The exponent of an RSA key is odd, at least 3, and 8 bytes at most.'];
        yield 'exponent one' => [['e' => Base64Url::encode("\x00\x01")] + $jwk, 'The exponent of an RSA key is odd, at least 3, and 8 bytes at most.'];
        yield 'empty exponent' => [['e' => ''] + $jwk, 'The exponent of an RSA key is odd, at least 3, and 8 bytes at most.'];
        yield 'long exponent' => [['e' => Base64Url::encode(str_repeat("\x01", 9))] + $jwk, 'The exponent of an RSA key is odd, at least 3, and 8 bytes at most.'];
    }

    /**
     * @param array<string, mixed> $jwk
     */
    #[DataProvider('badJwks')]
    public function testAMalformedJsonWebKeyIsRefused(array $jwk, string $message): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains($message);

        RsaPublicKey::fromJwk($jwk);
    }

    public function testTheBoundsOfAJsonWebKeyAreInclusive(): void
    {
        $jwk = Keys::platform()->publicKey->toJwk();

        self::assertSame(8, \strlen(Base64Url::decode('AQAAAAAAAAE')));
        self::assertSame('AQAAAAAAAAE', RsaPublicKey::fromJwk(['e' => 'AQAAAAAAAAE'] + $jwk)->toJwk()['e']);
        self::assertSame('Aw', RsaPublicKey::fromJwk(['e' => 'Aw'] + $jwk)->toJwk()['e']);
        self::assertSame('AQAB', RsaPublicKey::fromJwk(['e' => 'AAEAAQ'] + $jwk)->toJwk()['e']);
        self::assertSame($jwk['n'], RsaPublicKey::fromJwk(['n' => Base64Url::encode("\x00".Base64Url::decode($jwk['n']))] + $jwk)->toJwk()['n']);
        self::assertSame('RS256', RsaPublicKey::fromJwk(['alg' => 'RS256', 'use' => 'sig'] + $jwk)->toJwk()['alg']);
    }

    public function testAnEightThousandBitKeyIsRead(): void
    {
        $jwk = Keys::platform()->publicKey->toJwk();
        $modulus = "\xc1".random_bytes(1022)."\x01";

        $key = RsaPublicKey::fromJwk(['n' => Base64Url::encode($modulus)] + $jwk);
        self::assertSame(Base64Url::encode($modulus), $key->toJwk()['n']);
    }

    public function testAPrivateKeyIsReadBackFromItsJson(): void
    {
        $key = Keys::tool();
        $json = $key->toJson();

        self::assertStringNotContainsString('\\/', $json);
        self::assertSame($key->toJwk(), RsaPrivateKey::fromJson($json)->toJwk());
        self::assertSame('tool-1', RsaPrivateKey::fromJson($json)->keyId());
        self::assertSame('other', RsaPrivateKey::fromJson($json, 'other')->keyId());
        self::assertSame(65_536, RsaPrivateKey::MAX_JSON_BYTES);
        self::assertSame(4, RsaPrivateKey::MAX_JSON_DEPTH);
    }

    public function testTheSlashesOfAPrivateKeyJsonAreNotEscaped(): void
    {
        $json = Keys::tool()->toJson();
        $key = RsaPrivateKey::fromJson($json, 'tenant/key');

        self::assertStringContainsString('"kid":"tenant/key"', $key->toJson());
    }

    public function testTheJsonOfAPrivateKeyAtItsLongestIsRead(): void
    {
        $json = Keys::tool()->toJson();
        $padded = substr($json, 0, -1).',"x":"'.str_repeat('a', RsaPrivateKey::MAX_JSON_BYTES - \strlen($json) - 7).'"}';

        self::assertSame(RsaPrivateKey::MAX_JSON_BYTES, \strlen($padded));
        self::assertSame('tool-1', RsaPrivateKey::fromJson($padded)->keyId());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badPrivateJson(): iterable
    {
        yield 'too long' => [str_repeat(' ', RsaPrivateKey::MAX_JSON_BYTES + 1), 'A private JSON Web Key is 65536 bytes at most.'];
        yield 'not JSON' => ['{', 'The private key is not a JSON Web Key.'];
        yield 'too deep' => ['{"a":{"b":{"c":{"d":1}}}}', 'The private key is not a JSON Web Key.'];
        yield 'not an object' => ['"RSA"', 'The key is not an RSA private key.'];
        yield 'public only' => [json_encode(Keys::tool()->publicKey->toJwk(), \JSON_THROW_ON_ERROR), 'The key is not an RSA private key.'];
    }

    #[DataProvider('badPrivateJson')]
    public function testTheJsonOfAPrivateKeyIsChecked(string $json, string $message): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains($message);

        RsaPrivateKey::fromJson($json);
    }

    public function testTheJsonOfAPrivateKeyThreeLevelsDeepIsRead(): void
    {
        $jwk = Keys::tool()->toJwk();
        $jwk['x'] = ['y' => ['z']];

        self::assertSame('tool-1', RsaPrivateKey::fromJson(json_encode($jwk, \JSON_THROW_ON_ERROR))->keyId());
    }

    private function sign(RsaPrivateKey $key, string $data): string
    {
        return new RS256()->sign($key->jwk(), $data);
    }

    private function verify(RsaPublicKey $key, string $data, string $signature): bool
    {
        return new RS256()->verify($key->jwk, $data, $signature);
    }
}
