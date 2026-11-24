<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Http;

use Override;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SamlSp\Exception\MetadataUnavailable;
use Phil\SamlSp\Http\SafeHttpMetadataFetcher;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SafeHttpMetadataFetcher::class)]
final class SafeHttpMetadataFetcherTest extends TestCase
{
    private const array RESOLVE = ['idp.example.org' => '93.184.215.14'];

    public function testMetadataIsFetchedOverHttps(): void
    {
        $seen = [];
        $fetcher = $this->fetcher(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [$method, $url, $options['headers'] ?? []];

            return new MockResponse('<md:EntityDescriptor/>', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/samlmetadata+xml']]);
        });

        self::assertSame('<md:EntityDescriptor/>', $fetcher->fetch(Url::fromString('https://idp.example.org/metadata')));
        self::assertSame('GET', $seen[0]);
        self::assertSame('https://idp.example.org/metadata', $seen[1]);
        self::assertIsArray($seen[2]);
        self::assertContains('Accept: application/samlmetadata+xml, application/xml;q=0.9, text/xml;q=0.8', $seen[2]);
        self::assertContains('User-Agent: PhilSamlSp/0.1', $seen[2]);
    }

    public function testPlainHttpIsRefused(): void
    {
        $this->expectException(MetadataUnavailable::class);
        $this->expectExceptionMessageIsOrContains('Metadata is fetched over HTTPS; "http://idp.example.org/metadata" is not.');

        $this->fetcher(static fn (): MockResponse => new MockResponse(''))->fetch(Url::fromString('http://idp.example.org/metadata'));
    }

    public function testAnErrorStatusIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The metadata at "https://idp.example.org/metadata" answered 404, was too large, or was redirected away from HTTPS.');

        $this->fetcher(static fn (): MockResponse => new MockResponse('', ['http_code' => 404]))->fetch(Url::fromString('https://idp.example.org/metadata'));
    }

    public function testTooLargeIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('answered 200, was too large');

        new SafeHttpMetadataFetcher(new SafeHttpClient(new MockHttpClient(new MockResponse(str_repeat('a', 2048))), new Limits(maxBytes: 1024), self::RESOLVE))->fetch(Url::fromString('https://idp.example.org/metadata'));
    }

    public function testARedirectAwayFromHttpsIsRefused(): void
    {
        $responses = [new MockResponse('', ['http_code' => 302, 'response_headers' => ['location' => 'http://idp.example.org/plain']]), new MockResponse('<x/>')];

        $this->expectExceptionMessageIsOrContains('or was redirected away from HTTPS.');

        $this->fetcher(static function () use (&$responses): MockResponse {
            return array_shift($responses) ?? new MockResponse('');
        })->fetch(Url::fromString('https://idp.example.org/metadata'));
    }

    public function testAPrivateAddressIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The metadata at "https://idp.example.org/metadata" cannot be fetched:');

        new SafeHttpMetadataFetcher(new SafeHttpClient(new MockHttpClient(new MockResponse('')), SafeHttpMetadataFetcher::defaultLimits(), ['idp.example.org' => '10.0.0.1']))->fetch(Url::fromString('https://idp.example.org/metadata'));
    }

    public function testTheDefaultLimits(): void
    {
        $limits = SafeHttpMetadataFetcher::defaultLimits();

        self::assertSame([5.0, 15.0, 4_194_304, 3, 'PhilSamlSp/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);
        new SafeHttpMetadataFetcher()->reset();

        $client = new class(new MockResponse('')) extends MockHttpClient {
            public int $resets = 0;

            #[Override]
            public function reset(): void
            {
                ++$this->resets;
                parent::reset();
            }
        };
        new SafeHttpMetadataFetcher(new SafeHttpClient($client, SafeHttpMetadataFetcher::defaultLimits(), self::RESOLVE))->reset();
        self::assertSame(1, $client->resets);
        $this->fetcher(static fn (): MockResponse => new MockResponse(''))->reset();
    }

    private function fetcher(callable $factory): SafeHttpMetadataFetcher
    {
        return new SafeHttpMetadataFetcher(new SafeHttpClient(new MockHttpClient($factory), SafeHttpMetadataFetcher::defaultLimits(), self::RESOLVE));
    }
}
