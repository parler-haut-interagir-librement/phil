<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Key;

use LogicException;
use OpenSSLAsymmetricKey;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadges\Key\RsaSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

#[CoversClass(RsaKey::class)]
final class RsaKeyTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private static array $keys = [];

    public function testAJsonWebKeyVerifiesWhatItsPrivateKeySigned(): void
    {
        $signer = RsaSigner::fromPem($this->pem(2048));
        $key = RsaKey::fromJwk($signer->publicJwk());

        self::assertSame('RS256', $key->algorithm());
        self::assertTrue($key->verify('data', $signer->sign('data')));
        self::assertFalse($key->verify('other', $signer->sign('data')));
        self::assertFalse(RsaKey::fromJwk(RsaSigner::fromPem($this->pem(2048, 1))->publicJwk())->verify('data', $signer->sign('data')));
    }

    public function testTheKeyReadIsTheOneOfThePem(): void
    {
        $signer = RsaSigner::fromPem($this->pem(3072));
        $jwk = $signer->publicJwk();
        $key = openssl_pkey_get_details(self::opened(openssl_pkey_get_public($this->publicPem(3072))));
        self::assertIsArray($key);

        $fromJwk = new ReflectionProperty(RsaKey::class, 'key')->getValue(RsaKey::fromJwk($jwk));
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $fromJwk);
        self::assertSame($key['key'], openssl_pkey_get_details($fromJwk)['key'] ?? null);
        self::assertSame(3072, openssl_pkey_get_details($fromJwk)['bits'] ?? null);
    }

    public function testAModulusWithLeadingZerosAndAnRs256AlgorithmAreRead(): void
    {
        $signer = RsaSigner::fromPem($this->pem(2048));
        $jwk = $signer->publicJwk();
        $padded = ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => Base64Url::encode("\x00\x00".Base64Url::decode($jwk['n'])), 'e' => Base64Url::encode("\x00".Base64Url::decode($jwk['e'])), 'd' => 'ignored'];

        self::assertTrue(RsaKey::fromJwk($padded)->verify('data', $signer->sign('data')));
    }

    public function testTheLimitsOfTheModulusAreItsBits(): void
    {
        $n = Base64Url::decode(RsaSigner::fromPem($this->pem(2048))->publicJwk()['n']);

        self::assertSame("\x80", \chr(\ord($n[0]) & 0x80), 'A modulus of 2048 bits starts with its high bit set.');
        self::assertSame(2048, RsaKey::MIN_BITS);
        self::assertSame(8192, RsaKey::MAX_BITS);

        $this->expectExceptionObject(InvalidBadge::because('The modulus of an RSA JSON Web Key has 2048 to 8192 bits; this one has 2047.'));
        RsaKey::fromJwk(['kty' => 'RSA', 'n' => Base64Url::encode(\chr(\ord($n[0]) >> 1).substr($n, 1)), 'e' => 'AQAB']);
    }

    public function testAModulusOf8192BitsIsTheLargestRead(): void
    {
        $max = "\x80".str_repeat("\x01", 1023);

        try {
            RsaKey::fromJwk(['kty' => 'RSA', 'n' => Base64Url::encode("\x01".str_repeat("\x00", 1024)), 'e' => 'AQAB']);
            self::fail('A modulus of 8193 bits was read.');
        } catch (InvalidBadge $invalidBadge) {
            self::assertSame('The modulus of an RSA JSON Web Key has 2048 to 8192 bits; this one has 8193.', $invalidBadge->getMessage());
        }

        $key = RsaKey::fromJwk(['kty' => 'RSA', 'n' => Base64Url::encode($max), 'e' => 'AQAB']);
        self::assertFalse($key->verify('data', str_repeat("\x00", 1024)));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        $n = 'n'.str_repeat('A', 340);
        $shape = 'An RSA JSON Web Key has the type "RSA", its "n" and its "e".';
        $exponent = 'The exponent of an RSA JSON Web Key is odd, at least 3 and 8 bytes at most.';

        yield 'no type' => [['n' => $n, 'e' => 'AQAB'], $shape];
        yield 'another type' => [['kty' => 'OKP', 'n' => $n, 'e' => 'AQAB'], $shape];
        yield 'no modulus' => [['kty' => 'RSA', 'e' => 'AQAB'], $shape];
        yield 'no exponent' => [['kty' => 'RSA', 'n' => $n], $shape];
        yield 'a modulus not text' => [['kty' => 'RSA', 'n' => 7, 'e' => 'AQAB'], $shape];
        yield 'an exponent not text' => [['kty' => 'RSA', 'n' => $n, 'e' => 3], $shape];
        yield 'another algorithm' => [['kty' => 'RSA', 'alg' => 'RS512', 'n' => $n, 'e' => 'AQAB'], 'An RSA JSON Web Key verifies RS256 signatures only.'];
        yield 'not base 64' => [['kty' => 'RSA', 'n' => 'not base 64!', 'e' => 'AQAB'], 'The parts of an RSA JSON Web Key are in URL-safe base 64.'];
        yield 'an exponent not base 64' => [['kty' => 'RSA', 'n' => Base64Url::encode("\x80".str_repeat("\x01", 255)), 'e' => '+/'], 'The parts of an RSA JSON Web Key are in URL-safe base 64.'];
        yield 'an empty modulus' => [['kty' => 'RSA', 'n' => 'AAAA', 'e' => 'AQAB'], 'The modulus of an RSA JSON Web Key has 2048 to 8192 bits; this one has 0.'];
        yield 'an empty exponent' => [['kty' => 'RSA', 'n' => Base64Url::encode("\x80".str_repeat("\x01", 255)), 'e' => 'AA'], $exponent];
        yield 'an exponent of 1' => [['kty' => 'RSA', 'n' => Base64Url::encode("\x80".str_repeat("\x01", 255)), 'e' => 'AAE'], $exponent];
        yield 'an even exponent' => [['kty' => 'RSA', 'n' => Base64Url::encode("\x80".str_repeat("\x01", 255)), 'e' => Base64Url::encode("\x01\x00\x02")], $exponent];
        yield 'an exponent of 9 bytes' => [['kty' => 'RSA', 'n' => Base64Url::encode("\x80".str_repeat("\x01", 255)), 'e' => Base64Url::encode(str_repeat("\x01", 9))], $exponent];
    }

    /**
     * @param array<array-key, mixed> $jwk
     */
    #[DataProvider('refused')]
    public function testAKeyThatIsNotAPublicRsaJsonWebKeyIsRefused(array $jwk, string $reason): void
    {
        $this->expectExceptionObject(InvalidBadge::because($reason));
        RsaKey::fromJwk($jwk);
    }

    public function testAnExponentOf3And8BytesIsRead(): void
    {
        $n = Base64Url::encode("\x80".str_repeat("\x01", 255));

        self::assertSame('RS256', RsaKey::fromJwk(['kty' => 'RSA', 'n' => $n, 'e' => 'Aw'])->algorithm());
        self::assertSame('RS256', RsaKey::fromJwk(['kty' => 'RSA', 'n' => $n, 'e' => Base64Url::encode(str_repeat("\x01", 8))])->algorithm());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function lengths(): iterable
    {
        yield 'none' => [0, "\x00"];
        yield 'the longest short form' => [127, "\x7f"];
        yield 'the shortest long form' => [128, "\x82\x00\x80"];
        yield 'two bytes' => [300, "\x82\x01\x2c"];
    }

    #[DataProvider('lengths')]
    public function testTheLengthOfAnElementIsInTheShortFormUnder128BytesAndOnTwoBytesFromThere(int $length, string $encoded): void
    {
        $der = new ReflectionMethod(RsaKey::class, 'der');

        self::assertSame("\x04".$encoded.str_repeat('a', $length), $der->invoke(null, "\x04", str_repeat('a', $length)));
    }

    private function pem(int $bits, int $index = 0): string
    {
        return self::$keys[$bits * 10 + $index] ??= (static function (int $bits): string {
            $key = self::opened(openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]));
            openssl_pkey_export($key, $pem);

            return \is_string($pem) ? $pem : throw new LogicException();
        })($bits);
    }

    private function publicPem(int $bits): string
    {
        $details = openssl_pkey_get_details(self::opened(openssl_pkey_get_private($this->pem($bits))));

        return \is_array($details) && \is_string($details['key']) ? $details['key'] : throw new LogicException();
    }

    private static function opened(false|OpenSSLAsymmetricKey $key): OpenSSLAsymmetricKey
    {
        return false === $key ? throw new LogicException() : $key;
    }
}
