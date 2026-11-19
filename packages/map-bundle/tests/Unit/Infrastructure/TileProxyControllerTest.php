<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Infrastructure;

use Phil\MapBundle\Domain\Exception\TileUnavailable;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Phil\MapBundle\Infrastructure\Http\TileProxyController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(TileProxyController::class)]
#[CoversClass(TileUnavailable::class)]
final class TileProxyControllerTest extends TestCase
{
    public function testATileIsFetchedUnderTheSitesNameAndKept(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options['headers'], $options['max_redirects'], $options['timeout']];

            return new MockResponse('PNG', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/PNG; charset=binary']]);
        });
        $controller = new TileProxyController($this->source(), $http, new ArrayAdapter(), 'example.org maps', 3600, 1000, 2.5);

        $response = $controller(2, 1, 3);
        $again = $controller(2, 1, 3);

        self::assertSame([200, 'PNG', 'image/png', 'nosniff'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('Content-Type'), $response->headers->get('X-Content-Type-Options')]);
        self::assertSame('max-age=3600, public', $response->headers->get('Cache-Control'));
        self::assertSame('PNG', $again->getContent());
        self::assertCount(1, $requests, 'the second request is answered from the cache');
        [$method, $url, $headers, $redirects, $timeout] = $requests[0];
        self::assertSame(['GET', 'https://t.example.org/2/1/3.png', 0, 2.5], [$method, $url, $redirects, $timeout]);
        self::assertIsArray($headers);
        self::assertContains('User-Agent: example.org maps', $headers);
        self::assertContains('Accept: image/png, image/jpeg, image/webp', $headers);
        self::assertCount(2, array_filter($headers, static fn (mixed $header): bool => \is_string($header) && !str_starts_with($header, 'Accept-Encoding')), 'nothing of the visitor is forwarded');
    }

    public function testATileOutsideTheGridIsNotFetched(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => self::fail('Nothing is fetched.'));
        $controller = new TileProxyController($this->source(), $http, new ArrayAdapter(), 'ua');

        self::assertSame(404, $controller(1, 2, 0)->getStatusCode());
        self::assertSame(404, $controller(19, 0, 0)->getStatusCode());
    }

    public function testAFailureIsAnsweredBadGatewayAndNotKept(): void
    {
        $answers = [
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('<html>', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]),
            new MockResponse('PNG', ['http_code' => 200]),
            new MockResponse(str_repeat('x', 1001), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/webp']]),
            new MockResponse('', ['error' => 'connection refused']),
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://elsewhere.example/']]),
            new MockResponse('JPEG', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/jpeg']]),
        ];
        $controller = new TileProxyController($this->source(), new MockHttpClient($answers), new ArrayAdapter(), 'ua', 60, 1000);

        for ($i = 0; $i < 6; ++$i) {
            $response = $controller(0, 0, 0);
            self::assertSame([502, '', 'no-store, private'], [$response->getStatusCode(), $response->getContent(), $response->headers->get('Cache-Control')], 'answer '.$i);
        }

        self::assertSame('JPEG', $controller(0, 0, 0)->getContent());
    }

    public function testTheLimitIsCheckedWhileDownloading(): void
    {
        $chunks = static function (): iterable {
            yield str_repeat('x', 600);
            yield str_repeat('x', 600);
        };
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse($chunks(), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), new ArrayAdapter(), 'ua', 60, 1000);

        self::assertSame(502, $controller(0, 0, 0)->getStatusCode());
    }

    public function testATileOfExactlyTheLimitIsAccepted(): void
    {
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse(str_repeat('x', 1000), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), new ArrayAdapter(), 'ua', 60, 1000);

        self::assertSame(200, $controller(0, 0, 0)->getStatusCode());
    }

    public function testTheDefaults(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['timeout'];

            return new MockResponse(str_repeat('x', 524_288), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']]);
        });
        $response = new TileProxyController($this->source(), $http, new ArrayAdapter(), 'ua')(0, 0, 0);

        self::assertSame([200, 'max-age=604800, public'], [$response->getStatusCode(), $response->headers->get('Cache-Control')]);
        self::assertSame([5.0], $requests);
    }

    public function testTheTileIsKeptUnderAKeyOfTheSourceAndTheTile(): void
    {
        $cache = new ArrayAdapter();
        new TileProxyController($this->source(), new MockHttpClient(new MockResponse('A', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), $cache, 'ua', 3600)(2, 1, 3);

        $item = $cache->getItem('phil_map_tile.'.hash('xxh128', 'https://t.example.org/{z}/{x}/{y}.png').'.2.1.3');
        self::assertTrue($item->isHit());
        $expiry = $item->getMetadata()[ItemInterface::METADATA_EXPIRY] ?? null;
        self::assertIsFloat($expiry);
        self::assertEqualsWithDelta(time() + 3600, $expiry, 5.0);
    }

    public function testASourceTheTemplateOfWhichFailsForAnotherTileIsBadGateway(): void
    {
        $source = TileSource::of('https://t.example.org:1{x}/{z}/{y}', '© X', 18);
        $http = new MockHttpClient(static fn (): MockResponse => self::fail('Nothing is fetched.'));

        self::assertSame(502, new TileProxyController($source, $http, new ArrayAdapter(), 'ua')(18, 100_000, 0)->getStatusCode());
    }

    public function testTheLimitStopsTheDownload(): void
    {
        $produced = 0;
        $chunks = static function () use (&$produced): iterable {
            for ($i = 0; $i < 4; ++$i) {
                ++$produced;

                yield str_repeat('x', 600);
            }
        };
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse($chunks(), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), new ArrayAdapter(), 'ua', 60, 1000);

        self::assertSame(502, $controller(0, 0, 0)->getStatusCode());
        self::assertLessThan(4, $produced, 'the rest is not downloaded');
    }

    public function testOnlyAnAnswerOfStatus200IsAnImage(): void
    {
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse('', ['http_code' => 204, 'response_headers' => ['Content-Type' => 'image/png']])), new ArrayAdapter(), 'ua');

        self::assertSame(502, $controller(0, 0, 0)->getStatusCode());
    }

    public function testTheTypeIsReadWithoutItsSpaces(): void
    {
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse('PNG', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png ; charset=binary']])), new ArrayAdapter(), 'ua');

        self::assertSame('image/png', $controller(0, 0, 0)->headers->get('Content-Type'));
    }

    public function testTheLimitByDefaultIs524288Bytes(): void
    {
        $controller = new TileProxyController($this->source(), new MockHttpClient(new MockResponse(str_repeat('x', 524_289), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), new ArrayAdapter(), 'ua');

        self::assertSame(502, $controller(0, 0, 0)->getStatusCode());
    }

    public function testTheCacheIsKeyedByTheSourceToo(): void
    {
        $cache = new ArrayAdapter();
        new TileProxyController($this->source(), new MockHttpClient(new MockResponse('A', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), $cache, 'ua')(0, 0, 0);
        $other = TileSource::of('https://other.example.org/{z}/{x}/{y}.png', 'x');

        self::assertSame('B', new TileProxyController($other, new MockHttpClient(new MockResponse('B', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'image/png']])), $cache, 'ua')(0, 0, 0)->getContent());
    }

    private function source(): TileSource
    {
        return TileSource::of('https://t.example.org/{z}/{x}/{y}.png', '© X', 18);
    }
}
