<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use Jose\Component\Core\JWK;
use OpenSSLAsymmetricKey;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SigningAlgorithm::class)]
#[CoversClass(PrivateKey::class)]
#[CoversClass(Jws::class)]
#[CoversClass(VerificationFailed::class)]
final class JoseTest extends TestCase
{
    /** @var array<string, PrivateKey> */
    private static array $keys = [];

    #[DataProvider('algorithms')]
    public function testEachAlgorithmSignsAndVerifies(SigningAlgorithm $algorithm): void
    {
        $key = $this->key($algorithm);
        $token = Jws::encode(['sub' => 'ada', 'n' => 1], $key, ['typ' => 'at+jwt']);
        $public = new JWK($key->publicJwk());

        self::assertSame(['sub' => 'ada', 'n' => 1], Jws::decode($token, static fn (string $kid): ?JWK => $kid === $key->kid ? $public : null));
        self::assertSame(['alg' => $algorithm->value, 'kid' => $key->kid, 'typ' => 'at+jwt'], json_decode(Base64Url::decode(explode('.', $token)[0]), true));
        self::assertSame(['kty' => $algorithm->keyType(), 'use' => 'sig', 'alg' => $algorithm->value, 'kid' => $key->kid], \array_slice($key->publicJwk(), 0, 4));
        self::assertArrayNotHasKey('d', $key->publicJwk());
        $library = $key->jwk()->toPublic()->all();
        $published = $key->publicJwk();
        ksort($library);
        ksort($published);
        self::assertSame($library, $published);

        $restored = PrivateKey::fromMaterial($algorithm, $key->kid, $key->material());
        self::assertSame($key->material(), $restored->material());
        self::assertSame(['sub' => 'b'], Jws::decode(Jws::encode(['sub' => 'b'], $restored), static fn (): JWK => $public));
    }

    /**
     * @return iterable<string, array{SigningAlgorithm}>
     */
    public static function algorithms(): iterable
    {
        foreach (SigningAlgorithm::cases() as $algorithm) {
            yield $algorithm->value => [$algorithm];
        }
    }

    public function testTheCoseIdentifiersAndKeyTypes(): void
    {
        foreach (SigningAlgorithm::cases() as $algorithm) {
            self::assertSame($algorithm, SigningAlgorithm::fromCose($algorithm->cose()));
        }

        self::assertSame([-257, -7, -8], array_map(static fn (SigningAlgorithm $algorithm): int => $algorithm->cose(), SigningAlgorithm::cases()));
        self::assertSame(['RSA', 'EC', 'OKP'], array_map(static fn (SigningAlgorithm $algorithm): string => $algorithm->keyType(), SigningAlgorithm::cases()));
        self::assertNull(SigningAlgorithm::fromCose(-35));
    }

    public function testAnRsaKeyHas3072Bits(): void
    {
        $jwk = $this->key(SigningAlgorithm::RS256)->publicJwk();

        self::assertSame(384, \strlen(Base64Url::decode($jwk['n'])));
        self::assertSame('AQAB', $jwk['e']);
        self::assertSame(16, \strlen($this->key(SigningAlgorithm::RS256)->kid));
    }

    public function testAKeyKeepsTheIdentifierItIsGiven(): void
    {
        self::assertSame('mine', PrivateKey::generate(SigningAlgorithm::ES256, 'mine')->kid);
        self::assertSame('mine', PrivateKey::generate(SigningAlgorithm::ES256, 'mine')->publicJwk()['kid']);
        self::assertNotSame(PrivateKey::generate(SigningAlgorithm::ES256)->kid, PrivateKey::generate(SigningAlgorithm::ES256)->kid);
    }

    public function testAPemAndALibsodiumSecretAreReadToo(): void
    {
        foreach ([[SigningAlgorithm::RS256, \OPENSSL_KEYTYPE_RSA], [SigningAlgorithm::ES256, \OPENSSL_KEYTYPE_EC]] as [$algorithm, $type]) {
            $key = PrivateKey::fromMaterial($algorithm, 'pem', $this->pem($type, 2048));
            self::assertSame(['kty' => $algorithm->keyType(), 'use' => 'sig', 'alg' => $algorithm->value, 'kid' => 'pem'], \array_slice($key->publicJwk(), 0, 4));
            self::assertSame(['a' => 1], Jws::decode(Jws::encode(['a' => 1], $key), static fn (): JWK => new JWK($key->publicJwk())));
        }

        $pair = sodium_crypto_sign_keypair();
        $key = PrivateKey::fromMaterial(SigningAlgorithm::EdDSA, 'sodium', Base64Url::encode(sodium_crypto_sign_secretkey($pair)));
        self::assertSame(Base64Url::encode(sodium_crypto_sign_publickey($pair)), $key->publicJwk()['x']);
        $token = Jws::encode(['a' => 1], $key);
        [$header, $claims, $signature] = explode('.', $token);
        /** @var non-empty-string $raw */
        $raw = Base64Url::decode($signature);
        self::assertTrue(sodium_crypto_sign_verify_detached($raw, $header.'.'.$claims, sodium_crypto_sign_publickey($pair)));
    }

    public function testTheMaterialIsKeptWithItsSlashes(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::ES256, 'a/b');

        self::assertStringContainsString('"kid":"a/b"', $key->material());
    }

    public function testOnlyTheStringMembersOfTheKeyArePublished(): void
    {
        $members = json_decode($this->key(SigningAlgorithm::ES256)->material(), true);
        self::assertIsArray($members);
        $key = PrivateKey::fromMaterial(SigningAlgorithm::ES256, 'k', (string) json_encode($members + ['key_ops' => ['sign']]));

        self::assertSame(['sign'], $key->jwk()->get('key_ops'));
        self::assertArrayNotHasKey('key_ops', $key->publicJwk());
    }

    public function testTheMaterialMayNestAsDeepAsFourLevelsOfValues(): void
    {
        $members = json_decode($this->key(SigningAlgorithm::ES256)->material(), true);
        self::assertIsArray($members);

        self::assertSame('k', PrivateKey::fromMaterial(SigningAlgorithm::ES256, 'k', (string) json_encode($members + ['a' => [['b']]]))->kid);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The material is not a private key the provider reads.');

        PrivateKey::fromMaterial(SigningAlgorithm::ES256, 'k', (string) json_encode($members + ['a' => [[['b']]]]));
    }

    public function testAnRsaModulusIsCountedWithoutItsLeadingZeroBytes(): void
    {
        $key = static fn (string $modulus): string => (string) json_encode(['kty' => 'RSA', 'n' => Base64Url::encode($modulus), 'e' => 'AQAB', 'd' => 'ZA', 'p' => 'cA', 'q' => 'cQ']);

        self::assertSame('k', PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', $key("\x00".str_repeat("\x01", 256)))->kid);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An RSA signing key has a modulus of at least 2048 bits.');

        PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', $key("\x00".str_repeat("\x01", 255)));
    }

    public function testAnRsaKeyOfAtLeast2048BitsIsRead(): void
    {
        self::assertSame('RS256', PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', $this->pem(\OPENSSL_KEYTYPE_RSA, 2048))->publicJwk()['alg']);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An RSA signing key has a modulus of at least 2048 bits.');

        PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', $this->pem(\OPENSSL_KEYTYPE_RSA, 2040));
    }

    #[DataProvider('badMaterials')]
    public function testAMaterialThatIsNotAKeyOfTheAlgorithmIsRefused(SigningAlgorithm $algorithm, string $material, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PrivateKey::fromMaterial($algorithm, 'k', $material);
    }

    /**
     * @return iterable<string, array{SigningAlgorithm, string, string}>
     */
    public static function badMaterials(): iterable
    {
        $json = static fn (array $members): string => (string) json_encode($members);
        $ec = ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'eA', 'y' => 'eQ', 'd' => 'ZA'];
        $reads = 'The material is not a private key the provider reads.';

        yield 'nonsense' => [SigningAlgorithm::ES256, 'nonsense', $reads];
        yield 'not json' => [SigningAlgorithm::ES256, '{', $reads];
        yield 'deep json' => [SigningAlgorithm::ES256, '{"a":{"b":{"c":{"d":1}}}}', $reads];
        yield 'empty object' => [SigningAlgorithm::ES256, '{}', $reads];
        yield 'no type' => [SigningAlgorithm::ES256, $json(['crv' => 'P-256']), $reads];
        yield 'bad pem' => [SigningAlgorithm::ES256, "-----BEGIN PRIVATE KEY-----\nAAAA\n-----END PRIVATE KEY-----\n", $reads];
        yield 'short sodium secret' => [SigningAlgorithm::EdDSA, Base64Url::encode(str_repeat("\x01", 32)), $reads];
        yield 'long sodium secret' => [SigningAlgorithm::EdDSA, Base64Url::encode(str_repeat("\x01", 65)), $reads];
        yield 'other type' => [SigningAlgorithm::RS256, $json($ec), 'The material is not a private key of RS256.'];
        yield 'other curve' => [SigningAlgorithm::ES256, $json(['crv' => 'P-384'] + $ec), 'The material is not a private key of ES256.'];
        yield 'no curve' => [SigningAlgorithm::EdDSA, $json(['kty' => 'OKP', 'x' => 'eA', 'd' => 'ZA']), 'The material is not a private key of EdDSA.'];
        yield 'public only' => [SigningAlgorithm::ES256, $json(array_diff_key($ec, ['d' => true])), 'The material is not a private key of ES256.'];
        yield 'empty member' => [SigningAlgorithm::ES256, $json(['d' => ''] + $ec), 'The material is not a private key of ES256.'];
        yield 'number member' => [SigningAlgorithm::ES256, $json(['d' => 1] + $ec), 'The material is not a private key of ES256.'];
        yield 'no modulus' => [SigningAlgorithm::RS256, $json(['kty' => 'RSA', 'e' => 'ZQ', 'd' => 'ZA', 'p' => 'cA', 'q' => 'cQ']), 'The material is not a private key of RS256.'];
        yield 'no x on ec' => [SigningAlgorithm::ES256, $json(array_diff_key($ec, ['x' => true])), 'The material is not a private key of ES256.'];
        yield 'no x on okp' => [SigningAlgorithm::EdDSA, $json(['kty' => 'OKP', 'crv' => 'Ed25519', 'd' => 'ZA']), 'The material is not a private key of EdDSA.'];
        yield 'curve on rsa' => [SigningAlgorithm::RS256, $json(['kty' => 'RSA', 'crv' => 'P-256', 'n' => 'bg', 'e' => 'ZQ', 'd' => 'ZA', 'p' => 'cA', 'q' => 'cQ']), 'The material is not a private key of RS256.'];
    }

    public function testAKeyHasAnIdentifier(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A signing key has an identifier.');

        PrivateKey::fromMaterial(SigningAlgorithm::EdDSA, '', $this->key(SigningAlgorithm::EdDSA)->material());
    }

    #[DataProvider('malformedTokens')]
    public function testAMalformedTokenIsRefused(string $token, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        Jws::decode($token, static fn (): ?JWK => null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedTokens(): iterable
    {
        $part = static fn (mixed $value): string => Base64Url::encode((string) json_encode($value));

        yield 'too long' => [str_repeat('a', 8193), 'The token is too long.'];
        yield 'two parts' => ['a.b', 'A compact JSON Web Signature has three parts.'];
        yield 'four parts' => ['a.b.c.d', 'A compact JSON Web Signature has three parts.'];
        yield 'not json' => [Base64Url::encode('{').'.'.$part([]).'.', 'A part of the token is not JSON.'];
        yield 'a list' => [$part([1]).'.'.$part([]).'.', 'A part of the token is a JSON object.'];
        yield 'a string' => [$part('a').'.'.$part([]).'.', 'A part of the token is a JSON object.'];
        yield 'no alg' => [$part(['kid' => 'a']).'.'.$part(['a' => 1]).'.', 'The header names an algorithm and a key'];
        yield 'no kid' => [$part(['alg' => 'ES256']).'.'.$part(['a' => 1]).'.', 'The header names an algorithm and a key'];
        yield 'number kid' => [$part(['alg' => 'ES256', 'kid' => 1]).'.'.$part(['a' => 1]).'.', 'The header names an algorithm and a key'];
        yield 'crit' => [$part(['alg' => 'ES256', 'kid' => 'a', 'crit' => ['b64']]).'.'.$part(['a' => 1]).'.', 'no critical extension'];
        yield 'b64' => [$part(['alg' => 'ES256', 'kid' => 'a', 'b64' => false]).'.'.$part(['a' => 1]).'.', 'no critical extension'];
        yield 'deep' => [$part(['alg' => 'ES256', 'kid' => 'a']).'.'.Base64Url::encode('{"a":'.str_repeat('[', 16).'1'.str_repeat(']', 16).'}').'.', 'A part of the token is not JSON.'];
    }

    public function testATokenSignedWithAnotherKeyOrAlgorithmIsRefused(): void
    {
        $es = $this->key(SigningAlgorithm::ES256);
        $ed = $this->key(SigningAlgorithm::EdDSA);
        $esPublic = new JWK($es->publicJwk());
        $token = Jws::encode(['a' => 1], $es);
        [$header, $claims, $signature] = explode('.', $token);
        $part = static fn (mixed $value): string => Base64Url::encode((string) json_encode($value));

        $cases = [
            'unknown key' => [$token, static fn (): ?JWK => null, 'The token names a key the provider does not publish.'],
            'other algorithm' => [$token, static fn (): JWK => new JWK($ed->publicJwk()), 'The signature of the token does not verify.'],
            'key without algorithm' => [$token, static fn (): JWK => new JWK(array_diff_key($es->publicJwk(), ['alg' => true])), 'The signature of the token does not verify.'],
            'other claims' => [$header.'.'.Base64Url::encode('{"a":2}').'.'.$signature, static fn (): JWK => $esPublic, 'The signature of the token does not verify.'],
            'none' => [$part(['alg' => 'none', 'kid' => $es->kid]).'.'.$claims.'.', static fn (): JWK => $esPublic, 'The signature of the token does not verify.'],
            'hmac' => [$part(['alg' => 'HS256', 'kid' => $es->kid]).'.'.$claims.'.'.$signature, static fn (): JWK => $esPublic, 'The signature of the token does not verify.'],
            'signature not base64url' => [$header.'.'.$claims.'.***', static fn (): JWK => $esPublic, 'The signature of the token does not verify.'],
            'another key of the algorithm' => [Jws::encode(['a' => 1], PrivateKey::generate(SigningAlgorithm::ES256, $es->kid)), static fn (): JWK => $esPublic, 'The signature of the token does not verify.'],
        ];

        foreach ($cases as $case => [$forged, $keys, $message]) {
            try {
                Jws::decode($forged, $keys);
                self::fail($case);
            } catch (VerificationFailed $verificationFailed) {
                self::assertSame($message, $verificationFailed->getMessage(), $case);
            }
        }
    }

    public function testTheKidOfTheHeaderPicksTheKey(): void
    {
        $key = $this->key(SigningAlgorithm::EdDSA);
        $asked = [];
        Jws::decode(Jws::encode(['a' => 1], $key), static function (string $kid) use ($key, &$asked): JWK {
            $asked[] = $kid;

            return new JWK($key->publicJwk());
        });

        self::assertSame([$key->kid], $asked);
    }

    private function key(SigningAlgorithm $algorithm): PrivateKey
    {
        return self::$keys[$algorithm->value] ??= PrivateKey::generate($algorithm);
    }

    private function pem(int $type, int $bits): string
    {
        $key = openssl_pkey_new(\OPENSSL_KEYTYPE_RSA === $type ? ['private_key_type' => $type, 'private_key_bits' => $bits] : ['private_key_type' => $type, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        openssl_pkey_export($key, $pem);
        self::assertIsString($pem);

        return $pem;
    }
}
