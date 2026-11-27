<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Key;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Key\CachedKeySetSource;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\SafeHttpKeySetSource;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\StaticKeySets;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(CachedKeySetSource::class)]
#[CoversClass(SafeHttpKeySetSource::class)]
final class KeySetSourcesTest extends TestCase
{
    private const string JWKS = 'https://lms.example.org/jwks';

    public function testTheCacheReadsASetOnceUntilItExpires(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $source = new StaticKeySets()->with(self::JWKS, new KeySet([Keys::platform()->publicKey]));
        $cached = new CachedKeySetSource($source, new ArrayAdapter(), $clock, 100, 30);
        $url = Url::fromString(self::JWKS);

        self::assertSame('platform-1', $cached->keySet($url)->keys[0]->keyId);
        self::assertSame('platform-1', $cached->keySet($url)->keys[0]->keyId);
        self::assertSame([[self::JWKS, true]], $source->reads);
    }

    public function testARefreshReadsAgainOnlyAfterTheInterval(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $source = new StaticKeySets()->with(self::JWKS, new KeySet([Keys::platformNext()->publicKey]));
        $cached = new CachedKeySetSource($source, new ArrayAdapter(), $clock, 3600, 30);
        $url = Url::fromString(self::JWKS);

        $cached->keySet($url);
        $clock->sleep(29);
        $cached->keySet($url, true);
        self::assertSame(1, $source->count());

        $clock->sleep(1);
        $cached->keySet($url, true);
        self::assertSame(2, $source->count());

        $cached->keySet($url, true);
        self::assertSame(2, $source->count());
    }

    public function testARefreshOfAnUnknownAddressReadsIt(): void
    {
        $source = new StaticKeySets()->with(self::JWKS, new KeySet([Keys::platform()->publicKey]));
        $cached = new CachedKeySetSource($source, new ArrayAdapter(), new MockClock());

        self::assertCount(1, $cached->keySet(Url::fromString(self::JWKS), true)->keys);
        self::assertSame(1, $source->count());
        self::assertSame(3600, CachedKeySetSource::DEFAULT_LIFETIME);
        self::assertSame(60, CachedKeySetSource::DEFAULT_REFRESH_INTERVAL);
    }

    public function testTheLifetimeIsGivenToTheCache(): void
    {
        $clock = new MockClock('2026-11-14 12:00:00 UTC');
        $cache = new ArrayAdapter(clock: $clock);
        $source = new StaticKeySets()->with(self::JWKS, new KeySet([Keys::platform()->publicKey]));
        $cached = new CachedKeySetSource($source, $cache, $clock, 100);

        $cached->keySet(Url::fromString(self::JWKS));

        $clock->sleep(99);
        $cached->keySet(Url::fromString(self::JWKS));
        self::assertSame(1, $source->count());
        $clock->sleep(2);
        $cached->keySet(Url::fromString(self::JWKS));
        self::assertSame(2, $source->count());
    }

    public function testASetIsReadOverHttps(): void
    {
        /** @var list<array{string, string, list<string>}> $requests */
        $requests = [];
        $json = new KeySet([Keys::platform()->publicKey])->toJson();
        $source = $this->source(static function (string $method, string $url, array $options) use (&$requests, $json): MockResponse {
            $headers = $options['headers'] ?? [];
            $requests[] = [$method, $url, \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : []];

            return new MockResponse($json, ['response_headers' => ['Content-Type' => 'application/json']]);
        });

        self::assertSame('platform-1', $source->keySet(Url::fromString(self::JWKS))->keys[0]->keyId);
        self::assertSame('GET', $requests[0][0]);
        self::assertSame(self::JWKS, $requests[0][1]);
        self::assertContains('Accept: application/jwk-set+json, application/json;q=0.9', $requests[0][2]);
        $source->reset();
    }

    public function testAFailedReadIsAServiceFailure(): void
    {
        $cases = [
            'http' => [null, 'http://lms.example.org/jwks', 'A key set is read over HTTPS; "http://lms.example.org/jwks" is not.'],
            'status' => [new MockResponse('', ['http_code' => 404]), self::JWKS, 'The key set at "https://lms.example.org/jwks" answered 404.'],
            'large' => [new MockResponse(str_repeat(' ', KeySet::MAX_BYTES + 10)), self::JWKS, 'The key set at "https://lms.example.org/jwks" is too large, or was redirected away from HTTPS.'],
            'transport' => [new MockResponse('', ['error' => 'boom']), self::JWKS, 'The key set at "https://lms.example.org/jwks" cannot be read: '],
        ];

        foreach ($cases as $name => [$response, $url, $message]) {
            $source = $this->source(static fn (): MockResponse => $response ?? new MockResponse(''));

            try {
                $source->keySet(Url::fromString($url));
                self::fail($name.' was read.');
            } catch (ServiceFailed $serviceFailed) {
                self::assertStringStartsWith($message, $serviceFailed->getMessage(), $name);
                self::assertSame('status' === $name ? 404 : 0, $serviceFailed->status());
            }
        }
    }

    public function testARedirectAwayFromHttpsIsRefused(): void
    {
        $source = $this->source(static fn (string $method, string $url): MockResponse => str_starts_with($url, 'https') ? new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://lms.example.org/other']]) : new MockResponse('{"keys":[]}'));

        $this->expectExceptionMessageIsOrContains('The key set at "https://lms.example.org/jwks" cannot be read: The redirect from "https://lms.example.org/jwks" to "http://lms.example.org/other" leaves https for http');
        $source->keySet(Url::fromString(self::JWKS));
    }

    public function testADocumentThatIsNoSetIsAnInvalidToken(): void
    {
        $this->expectException(InvalidToken::class);
        $this->source(static fn (): MockResponse => new MockResponse('{}'))->keySet(Url::fromString(self::JWKS));
    }

    public function testTheDefaultClientIsBuilt(): void
    {
        $this->expectException(ServiceFailed::class);
        new SafeHttpKeySetSource()->keySet(Url::fromString('http://lms.example.org/jwks'));
    }

    public function testTheDefaultLimitsOfTheKeySets(): void
    {
        $limits = SafeHttpKeySetSource::defaultLimits();

        self::assertSame([5.0, 10.0, KeySet::MAX_BYTES, 2, 'PhilLti/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);
    }

    private function source(callable $factory): SafeHttpKeySetSource
    {
        return new SafeHttpKeySetSource(new SafeHttpClient(new MockHttpClient($factory), new Limits(maxBytes: KeySet::MAX_BYTES), ['lms.example.org' => '93.184.215.14']));
    }
}
