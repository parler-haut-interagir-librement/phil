<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Key;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Base64Url;
use Phil\Lti\Key\CachedKeySetSource;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\StaticKeySets;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * The names a cache keeps key sets under, the depth and the size a set is read at, and the sizes of a modulus.
 */
#[CoversClass(CachedKeySetSource::class)]
#[CoversClass(SafeHttpKeySetSource::class)]
#[CoversClass(KeySet::class)]
#[CoversClass(RsaPublicKey::class)]
final class KeyEdgesTest extends TestCase
{
    public function testASetIsCachedUnderTheHashOfItsAddress(): void
    {
        $cache = new ArrayAdapter();
        $url = Url::fromString('https://lms.example.org/jwks');
        $source = new StaticKeySets()->with($url->toString(), new KeySet([Keys::platform()->publicKey]));

        new CachedKeySetSource($source, $cache, new MockClock('2026-11-14 12:00:00 UTC'))->keySet($url);

        self::assertTrue($cache->hasItem('phil_lti.jwks.'.hash('xxh128', $url->toString())));
    }

    public function testTheDefaultClientOfTheSourceIsBoundedAndSendsItsName(): void
    {
        $source = new SafeHttpKeySetSource();
        $client = new ReflectionProperty($source, 'client')->getValue($source);
        self::assertInstanceOf(SafeHttpClient::class, $client);
        $limits = new ReflectionProperty($client, 'limits')->getValue($client);
        self::assertInstanceOf(Limits::class, $limits);

        self::assertSame([5.0, 10.0, KeySet::MAX_BYTES, 2, 'PhilLti/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);
    }

    public function testTheSourceIsResetWithItsClient(): void
    {
        $inner = new class extends MockHttpClient {
            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
                parent::reset();
            }
        };

        new SafeHttpKeySetSource(new SafeHttpClient($inner))->reset();

        self::assertSame(1, $inner->resets);
    }

    public function testASetIsReadAtEightLevels(): void
    {
        $keys = [Keys::platform()->publicKey->toJwk()];

        self::assertCount(1, KeySet::fromJson(json_encode(['keys' => $keys, 'extra' => self::nested(6)], \JSON_THROW_ON_ERROR))->keys);

        $this->expectExceptionMessageIsOrContains('The key set is not JSON.');
        KeySet::fromJson(json_encode(['keys' => $keys, 'extra' => self::nested(7)], \JSON_THROW_ON_ERROR));
    }

    public function testTheSlashesOfASetAreNotEscaped(): void
    {
        $key = RsaPublicKey::fromPem(Keys::publicPem('platform'), 'a/b');

        self::assertStringContainsString('"kid":"a/b"', new KeySet([$key])->toJson());
    }

    public function testTheModulusIsOfTwoThousandToEightThousandBits(): void
    {
        foreach ([2048, 8192] as $bits) {
            self::assertSame(Base64Url::encode($this->modulus($bits)), RsaPublicKey::fromJwk($this->jwk($bits))->toJwk()['n'], $bits.' bits');
        }

        foreach ([2047, 8193] as $bits) {
            try {
                RsaPublicKey::fromJwk($this->jwk($bits));
                self::fail($bits.' bits were accepted.');
            } catch (InvalidToken $exception) {
                self::assertStringContainsString('The modulus of an RSA key has 2048 to 8192 bits', $exception->getMessage());
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function jwk(int $bits): array
    {
        return ['kty' => 'RSA', 'n' => Base64Url::encode($this->modulus($bits)), 'e' => 'AQAB'];
    }

    /**
     * A modulus of exactly this many bits, whose first byte is the smallest the size allows: 0x80 on a multiple of eight.
     */
    private function modulus(int $bits): string
    {
        $bytes = intdiv($bits + 7, 8);
        $first = 0 === $bits % 8 ? 0x80 : 1 << (($bits % 8) - 1);

        return \chr($first & 0xFF).str_repeat("\xa5", $bytes - 2)."\x01";
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function nested(int $levels): array
    {
        return 1 === $levels ? [] : [self::nested($levels - 1)];
    }
}
