<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Http;

use Phil\Lti\Http\LinkHeader;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\UrlPath;
use Phil\Lti\Http\UrlQuery;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * What the transport sends and what a header of links is made of, to the byte.
 */
#[CoversClass(LinkHeader::class)]
#[CoversClass(SafeHttpServiceClient::class)]
#[CoversClass(UrlPath::class)]
#[CoversClass(UrlQuery::class)]
final class HttpEdgesTest extends TestCase
{
    private const array RESOLVE = ['lms.example.org' => '93.184.215.14'];

    public function testTheLimitsOfARequestAreGivenToTheClient(): void
    {
        /** @var array<string, mixed> $seen */
        $seen = [];
        $client = new SafeHttpServiceClient(new SafeHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options;

            return new MockResponse('{}');
        }), SafeHttpServiceClient::defaultLimits(), self::RESOLVE));

        $client->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x')));

        self::assertSame(5.0, $seen['timeout']);
        self::assertIsArray($seen['headers']);
        self::assertContains('Accept: */*', $seen['headers']);
        self::assertIsFloat($seen['max_duration']);
        self::assertGreaterThan(19.0, $seen['max_duration']);
        self::assertLessThanOrEqual(20.0, $seen['max_duration']);
        self::assertSame(0, $seen['max_redirects']);
        self::assertSame(self::RESOLVE, $seen['resolve']);
        self::assertNull($seen['proxy']);
        self::assertSame('*', $seen['no_proxy']);
        self::assertFalse($seen['buffer']);
    }

    public function testTheHeadersAfterTheAcceptOneAreSentToo(): void
    {
        /** @var array<string, mixed> $seen */
        $seen = [];
        $client = new SafeHttpServiceClient(new SafeHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = $options;

            return new MockResponse('{}');
        }), SafeHttpServiceClient::defaultLimits(), self::RESOLVE));

        $client->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x'), ['accept' => 'application/json', 'X-After' => 'yes']));

        self::assertIsArray($seen['headers']);
        self::assertContains('Accept: application/json', $seen['headers']);
        self::assertContains('X-After: yes', $seen['headers']);
    }

    public function testAnAnswerOfAnErrorIsReturnedAsIs(): void
    {
        $client = new SafeHttpServiceClient(new SafeHttpClient(new MockHttpClient(new MockResponse('{"error":"x"}', ['http_code' => 404, 'response_headers' => ['X-Hint' => 'a']])), SafeHttpServiceClient::defaultLimits(), self::RESOLVE));

        $response = $client->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x')));

        self::assertSame(404, $response->status);
        self::assertSame('a', $response->header('X-Hint'));
    }

    public function testTheClientIsResetWithTheService(): void
    {
        $inner = new class extends MockHttpClient {
            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
                parent::reset();
            }
        };

        new SafeHttpServiceClient(new SafeHttpClient($inner))->reset();

        self::assertSame(1, $inner->resets);
    }

    public function testTheDefaultLimits(): void
    {
        $limits = SafeHttpServiceClient::defaultLimits();

        self::assertSame([5.0, 20.0, 4_194_304, 0, 'PhilLti/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);
    }

    public function testTheLinksOfAHeaderAreReadWhateverTheirShape(): void
    {
        $base = Url::fromString('https://lms.example.org/c/1/members');
        $padding = static fn (int $length): string => '</n>; rel=next; title="'.str_repeat('a', $length - 24).'"';

        self::assertSame('https://lms.example.org/n', LinkHeader::find([$padding(LinkHeader::MAX_LENGTH)], 'next', $base)?->toString());
        self::assertNull(LinkHeader::find([$padding(LinkHeader::MAX_LENGTH + 1)], 'next', $base));
        self::assertSame('https://lms.example.org/n', LinkHeader::find([$padding(LinkHeader::MAX_LENGTH + 1), '</n>; rel=next'], 'next', $base)?->toString());
        self::assertSame('https://lms.example.org/n', LinkHeader::find(['<https://lms.example.org/x>; title="x", </n>; rel=next'], 'next', $base)?->toString());
        self::assertSame('https://lms.example.org/n', LinkHeader::find(['</n>; REL=next'], 'next', $base)?->toString());
        self::assertSame('https://lms.example.org/n', LinkHeader::find(['</n>; rel=" next "'], 'next', $base)?->toString());
        self::assertSame('https://lms.example.org/n', LinkHeader::find(['</n>; rel="NEXT"'], 'next', $base)?->toString());
        self::assertSame('https://lms.example.org/n', LinkHeader::find(['</n>; rel=Next'], 'NEXT', $base)?->toString());
        self::assertNull(LinkHeader::find(['<https://tool.localhost/x>; rel=next'], 'next', $base));
    }

    public function testAnAddressOnTheLocalNetworkIsBuiltOnlyWhenAllowed(): void
    {
        $local = Url::fromString('https://tool.localhost/a', true);

        self::assertSame('https://tool.localhost/a/s', UrlPath::append($local, 's', true)->toString());
        self::assertSame('https://tool.localhost/a?x=1', UrlQuery::with($local, ['x' => '1'], true)->toString());

        foreach ([static fn (): Url => UrlPath::append($local, 's'), static fn (): Url => UrlQuery::with($local, ['x' => '1'])] as $build) {
            try {
                $build();
                self::fail('An address on the local network was built.');
            } catch (InvalidUrl) {
                self::addToAssertionCount(1);
            }
        }
    }
}
