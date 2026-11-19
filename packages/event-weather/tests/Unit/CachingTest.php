<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit;

use Phil\EventWeather\Contact;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\MetNorway\LocationforecastProvider;
use Phil\Geo\Coordinates;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpClient\CachingHttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The cache the documentation recommends: Symfony's `CachingHttpClient` around the client given to the providers
 * keeps an answer until its `Expires`, then asks again with `If-Modified-Since`, as MET Norway's terms require.
 */
#[CoversClass(LocationforecastProvider::class)]
final class CachingTest extends TestCase
{
    public function testAnAnswerIsKeptUntilItExpiresThenRevalidated(): void
    {
        $body = (string) file_get_contents(__DIR__.'/../Fixtures/met-locationforecast.json');
        $requests = [];
        $responses = [
            new MockResponse($body, ['response_headers' => ['Content-Type' => 'application/json', 'Expires' => gmdate('D, d M Y H:i:s', time() + 3_600).' GMT', 'Last-Modified' => 'Fri, 13 Nov 2026 10:41:07 GMT']]),
        ];
        $mock = new MockHttpClient(
            /**
             * @param array<string, mixed> $options
             */
            static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
                $requests[] = $options['normalized_headers'] ?? [];

                return array_shift($responses) ?? new MockResponse('', ['http_code' => 304, 'response_headers' => ['Expires' => gmdate('D, d M Y H:i:s', time() + 3_600).' GMT']]);
            }
        );
        $provider = new LocationforecastProvider(new CachingHttpClient($mock, new TagAwareAdapter(new ArrayAdapter()), sharedCache: false), new Contact('EventsApp/1.4', 'ops@events.example'));
        $location = new EventLocation(Coordinates::of(45.5017, -73.5673));

        $first = $provider->forecast($location);
        $second = $provider->forecast($location);

        self::assertCount(1, $requests);
        self::assertEquals($first, $second);
    }

    public function testAStaleAnswerIsAskedAgainWithIfModifiedSince(): void
    {
        $body = (string) file_get_contents(__DIR__.'/../Fixtures/met-locationforecast.json');
        $requests = [];
        $responses = [
            new MockResponse($body, ['response_headers' => ['Content-Type' => 'application/json', 'Expires' => 'Fri, 13 Nov 2026 10:41:07 GMT', 'Last-Modified' => 'Fri, 13 Nov 2026 10:41:07 GMT', 'Date' => 'Fri, 13 Nov 2026 10:41:07 GMT']]),
            new MockResponse('', ['http_code' => 304, 'response_headers' => ['Expires' => 'Fri, 13 Nov 2026 10:41:07 GMT', 'Last-Modified' => 'Fri, 13 Nov 2026 10:41:07 GMT']]),
        ];
        $mock = new MockHttpClient(
            /**
             * @param array<string, mixed> $options
             */
            static function (string $method, string $url, array $options) use (&$requests, &$responses): MockResponse {
                $headers = $options['normalized_headers'] ?? null;
                $requests[] = \is_array($headers) && \is_array($headers['if-modified-since'] ?? null) ? ($headers['if-modified-since'][0] ?? null) : null;

                return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
            }
        );
        $provider = new LocationforecastProvider(new CachingHttpClient($mock, new TagAwareAdapter(new ArrayAdapter()), sharedCache: false), new Contact('EventsApp/1.4', 'ops@events.example'));
        $location = new EventLocation(Coordinates::of(45.5017, -73.5673));

        $provider->forecast($location);
        $revalidated = $provider->forecast($location);

        self::assertSame([null, 'If-Modified-Since: Fri, 13 Nov 2026 10:41:07 GMT'], $requests);
        self::assertCount(3, $revalidated->periods);
    }
}
