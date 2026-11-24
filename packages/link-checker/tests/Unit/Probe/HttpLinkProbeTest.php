<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Probe;

use Generator;
use LogicException;
use Override;
use Phil\LinkChecker\Contract\HostThrottle;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Probe\HopRecorder;
use Phil\LinkChecker\Probe\HttpLinkProbe;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\LinkChecker\Tests\Support\RecordingThrottle;
use Phil\SafeHttp\Limits;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(HttpLinkProbe::class)]
#[CoversClass(HopRecorder::class)]
#[CoversClass(ProbeResult::class)]
#[CoversClass(LinkResult::class)]
final class HttpLinkProbeTest extends TestCase
{
    /** Public addresses of the documentation range, pinned so that no test resolves a name. */
    private const array RESOLVE = [
        'example.com' => '93.184.215.14',
        'www.example.com' => '93.184.215.14',
        'example.org' => '93.184.215.15',
        'tls.example' => '93.184.215.16',
        'internal.example' => '10.0.0.7',
    ];

    /** @var list<array{string, string, array<array-key, mixed>}> */
    private array $requests = [];

    private RecordingThrottle $throttle;

    #[Override]
    protected function setUp(): void
    {
        $this->throttle = new RecordingThrottle();
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function probe(array $responses, ?Limits $limits = null): HttpLinkProbe
    {
        $this->requests = [];
        $this->throttle = new RecordingThrottle();
        $requests = &$this->requests;
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$responses, &$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return array_shift($responses) ?? throw new LogicException('No more answers.');
        });

        return $limits instanceof Limits
            ? new HttpLinkProbe($client, $limits, self::RESOLVE, $this->throttle)
            : new HttpLinkProbe($client, resolve: self::RESOLVE, throttle: $this->throttle);
    }

    /**
     * @param array<string, string> $headers
     */
    private function answer(int $code, array $headers = [], string $body = ''): MockResponse
    {
        return new MockResponse($body, ['http_code' => $code, 'response_headers' => $headers]);
    }

    /**
     * @return list<string>
     */
    private function methods(): array
    {
        return array_map(static fn (array $request): string => $request[0], $this->requests);
    }

    private function header(int $request, string $name): ?string
    {
        $headers = $this->requests[$request][2]['normalized_headers'] ?? null;
        $lines = \is_array($headers) ? ($headers[strtolower($name)] ?? null) : null;
        $line = \is_array($lines) ? ($lines[0] ?? null) : null;

        return \is_string($line) ? substr($line, \strlen($name) + 2) : null;
    }

    public function testASuccessIsAliveAfterOneHeadRequest(): void
    {
        $result = $this->probe([$this->answer(200)])->probe(Url::fromString('https://example.com/page'));

        self::assertEquals(new ProbeResult(new LinkResult(LinkStatus::Alive, 200)), $result);
        self::assertSame(['HEAD'], $this->methods());
        self::assertSame('https://example.com/page', $this->requests[0][1]);
        self::assertSame('PhilLinkChecker/0.1', $this->header(0, 'User-Agent'));
        self::assertSame(['example.com'], $this->throttle->hosts);
    }

    public function testTheUserAgentIsTheLimitsOne(): void
    {
        $this->probe([$this->answer(204)], new Limits(userAgent: 'SiteLinks/2 (+https://example.org/bot)'))->probe(Url::fromString('https://example.com/'));

        self::assertSame('SiteLinks/2 (+https://example.org/bot)', $this->header(0, 'User-Agent'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function headRefused(): iterable
    {
        yield 'method not allowed' => [405];
        yield 'not implemented' => [501];
    }

    #[DataProvider('headRefused')]
    public function testAGetWithoutBodyFollowsAHeadTheServerRefuses(int $code): void
    {
        $result = $this->probe([$this->answer($code), $this->answer(200, ['Content-Type' => 'text/html'], '<p>body</p>')])->probe(Url::fromString('https://example.com/page'));

        self::assertEquals(new ProbeResult(new LinkResult(LinkStatus::Alive, 200)), $result);
        self::assertSame(['HEAD', 'GET'], $this->methods());
        self::assertSame('*/*', $this->header(1, 'Accept'));
        self::assertSame(['example.com', 'example.com'], $this->throttle->hosts);
    }

    /**
     * A page whose body says, through `$read`, whether it was read past its first chunk.
     */
    private function page(bool &$read): MockResponse
    {
        $body = static function () use (&$read): Generator {
            yield '<p id="a">';
            $read = true;

            yield 'body</p>';
        };

        return new MockResponse($body(), ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]);
    }

    public function testTheGetAfterARefusedHeadReadsNoBody(): void
    {
        // A body that stalls after its first chunk: read, it would end in a timeout.
        $stalled = new MockResponse(['<p>', '', 'rest</p>'], ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]);
        $result = $this->probe([self::answer(405), $stalled], new Limits(timeout: 0.1))->probe(Url::fromString('https://example.com/page'));

        self::assertEquals(new LinkResult(LinkStatus::Alive, 200), $result->result);
    }

    public function testAPageThatStallsIsATimeout(): void
    {
        $stalled = new MockResponse(['<p>', '', 'rest</p>'], ['http_code' => 200, 'response_headers' => ['Content-Type' => 'text/html']]);
        $result = $this->probe([$stalled], new Limits(timeout: 0.1))->probe(Url::fromString('https://example.com/page'), true);

        self::assertEquals(new LinkResult(LinkStatus::Review, reason: 'timeout'), $result->result);
    }

    public function testTheGetOfAPageReadsItsBody(): void
    {
        $read = false;
        $result = $this->probe([$this->page($read)])->probe(Url::fromString('https://example.com/page'), true);

        self::assertTrue($read);
        self::assertSame('<p id="a">body</p>', $result->html);
    }

    public function testAGetAfterARefusedHeadCanBeDead(): void
    {
        $result = $this->probe([$this->answer(405), $this->answer(404)])->probe(Url::fromString('https://example.com/gone'));

        self::assertEquals(new LinkResult(LinkStatus::Dead, 404), $result->result);
    }

    /**
     * @return iterable<string, array{int, LinkStatus}>
     */
    public static function codes(): iterable
    {
        yield '200' => [200, LinkStatus::Alive];
        yield '299' => [299, LinkStatus::Alive];
        yield '404' => [404, LinkStatus::Dead];
        yield '410' => [410, LinkStatus::Dead];
        yield '199' => [199, LinkStatus::Review];
        yield '300' => [300, LinkStatus::Review];
        yield '304' => [304, LinkStatus::Review];
        yield '400' => [400, LinkStatus::Review];
        yield '401' => [401, LinkStatus::Review];
        yield '403' => [403, LinkStatus::Review];
        yield '405 then 405' => [405, LinkStatus::Review];
        yield '429' => [429, LinkStatus::Review];
        yield '500' => [500, LinkStatus::Review];
        yield '503' => [503, LinkStatus::Review];
    }

    #[DataProvider('codes')]
    public function testEachCodeIsClassified(int $code, LinkStatus $status): void
    {
        $result = $this->probe([$this->answer($code), $this->answer($code)])->probe(Url::fromString('https://example.com/'));

        self::assertEquals(new LinkResult($status, $code), $result->result);
    }

    public function testPermanentRedirectsGiveTheirTarget(): void
    {
        $result = $this->probe([
            $this->answer(301, ['Location' => '/moved']),
            $this->answer(308, ['Location' => 'https://www.example.com/final']),
            $this->answer(200),
        ])->probe(Url::fromString('https://example.com/old'));

        self::assertEquals(new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://www.example.com/final')), $result->result);
        self::assertSame(['HEAD', 'HEAD', 'HEAD'], $this->methods());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function temporaryChains(): iterable
    {
        yield '301 then 302' => [301, 302];
        yield '302 then 301' => [302, 301];
        yield '307 then 308' => [307, 308];
        yield '308 then 303' => [308, 303];
    }

    #[DataProvider('temporaryChains')]
    public function testOneTemporaryRedirectMakesTheChainTemporary(int $first, int $second): void
    {
        $result = $this->probe([
            $this->answer($first, ['Location' => '/one']),
            $this->answer($second, ['Location' => '/two']),
            $this->answer(200),
        ])->probe(Url::fromString('https://example.com/old'));

        self::assertEquals(new LinkResult(LinkStatus::TemporaryRedirect, 200, Url::fromString('https://example.com/two')), $result->result);
    }

    public function testARedirectToADeadPageIsDeadWithItsTarget(): void
    {
        $result = $this->probe([$this->answer(302, ['Location' => '/gone']), $this->answer(410)])->probe(Url::fromString('https://example.com/old'));

        self::assertEquals(new LinkResult(LinkStatus::Dead, 410, Url::fromString('https://example.com/gone')), $result->result);
    }

    public function testARedirectToAPageToReviewKeepsItsTarget(): void
    {
        $result = $this->probe([$this->answer(301, ['Location' => '/busy']), $this->answer(429)])->probe(Url::fromString('https://example.com/old'));

        self::assertEquals(new LinkResult(LinkStatus::Review, 429, Url::fromString('https://example.com/busy')), $result->result);
    }

    public function testTheRedirectsOfTheGetAfterARefusedHeadAreItsOwn(): void
    {
        $result = $this->probe([$this->answer(405), $this->answer(301, ['Location' => '/new']), $this->answer(200)])->probe(Url::fromString('https://example.com/old'));

        self::assertEquals(new LinkResult(LinkStatus::PermanentRedirect, 200, Url::fromString('https://example.com/new')), $result->result);
        self::assertSame(['HEAD', 'GET', 'GET'], $this->methods());
    }

    public function testEachCheckForgetsTheAnswersOfThePreviousOne(): void
    {
        $probe = $this->probe([
            $this->answer(302, ['Location' => '/a']),
            $this->answer(200),
            $this->answer(301, ['Location' => '/b']),
            $this->answer(200),
        ]);

        self::assertSame(LinkStatus::TemporaryRedirect, $probe->probe(Url::fromString('https://example.com/1'))->result->status);
        self::assertSame(LinkStatus::PermanentRedirect, $probe->probe(Url::fromString('https://example.com/2'))->result->status);
    }

    public function testAnAddressThatIsNotPublicIsRefused(): void
    {
        $result = $this->probe([$this->answer(200)])->probe(Url::fromString('https://internal.example/admin'));

        self::assertEquals(new LinkResult(LinkStatus::Refused, reason: 'not a public address'), $result->result);
        self::assertSame([], $this->requests);
    }

    public function testARedirectToAPrivateNetworkIsRefused(): void
    {
        $result = $this->probe([$this->answer(302, ['Location' => 'https://internal.example/'])])->probe(Url::fromString('https://example.com/'));

        self::assertEquals(new LinkResult(LinkStatus::Refused, reason: 'not a public address'), $result->result);
    }

    public function testTooManyRedirectsAreToReview(): void
    {
        $result = $this->probe([
            $this->answer(301, ['Location' => '/1']),
            $this->answer(301, ['Location' => '/2']),
        ], new Limits(maxRedirects: 1))->probe(Url::fromString('https://example.com/'));

        self::assertEquals(new LinkResult(LinkStatus::Review, reason: 'too many redirects'), $result->result);
    }

    /**
     * @return iterable<string, array{string, LinkResult}>
     */
    public static function failures(): iterable
    {
        yield 'a name that does not resolve' => ['Could not resolve host: example.com', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'getaddrinfo' => ['php_network_getaddresses: getaddrinfo for example.com failed', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'name or service not known' => ['Name or service not known', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'nodename nor servname' => ['nodename nor servname provided, or not known', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'no address associated' => ['No address associated with hostname', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'name resolution' => ['Temporary failure in name resolution', new LinkResult(LinkStatus::Dead, reason: 'host not found')];
        yield 'a certificate' => ['SSL certificate problem: unable to get local issuer certificate', new LinkResult(LinkStatus::Review, reason: 'TLS error')];
        yield 'a TLS handshake' => ['TLS connect error: error:0A000410', new LinkResult(LinkStatus::Review, reason: 'TLS error')];
        yield 'a certificate name' => ['No alternative certificate subject name matches', new LinkResult(LinkStatus::Review, reason: 'TLS error')];
        yield 'a timeout' => ['Idle timeout reached', new LinkResult(LinkStatus::Review, reason: 'timeout')];
        yield 'timed out' => ['Operation timed out after 5001 milliseconds', new LinkResult(LinkStatus::Review, reason: 'timeout')];
        yield 'max duration' => ['Max duration was reached', new LinkResult(LinkStatus::Review, reason: 'timeout')];
        yield 'a refused connection' => ['Connection refused', new LinkResult(LinkStatus::Review, reason: 'connection failed')];
        yield 'an address that looks like TLS' => ['Connection refused for "https://tls.example/"', new LinkResult(LinkStatus::Review, reason: 'connection failed')];
    }

    #[DataProvider('failures')]
    public function testAReadThatFailsIsClassifiedByItsCause(string $error, LinkResult $expected): void
    {
        $result = $this->probe([new MockResponse('', ['error' => $error])])->probe(Url::fromString('https://tls.example/'));

        self::assertEquals($expected, $result->result);
    }

    public function testThePageOfALinkIsReadWhenAskedFor(): void
    {
        $result = $this->probe([$this->answer(200, ['Content-Type' => 'text/html; charset=utf-8'], '<h2 id="a">A</h2>')])->probe(Url::fromString('https://example.com/page'), true);

        self::assertEquals(new ProbeResult(new LinkResult(LinkStatus::Alive, 200), '<h2 id="a">A</h2>', true), $result);
        self::assertSame(['GET'], $this->methods());
        self::assertSame('text/html, application/xhtml+xml;q=0.9, */*;q=0.1', $this->header(0, 'Accept'));
        self::assertSame(['example.com'], $this->throttle->hosts);
    }

    public function testAnXhtmlPageIsRead(): void
    {
        $result = $this->probe([$this->answer(200, ['Content-Type' => 'application/xhtml+xml'], '<p id="b"/>')])->probe(Url::fromString('https://example.com/page'), true);

        self::assertSame('<p id="b"/>', $result->html);
    }

    public function testAnAnswerThatIsNotHtmlGivesNoPage(): void
    {
        $result = $this->probe([$this->answer(200, ['Content-Type' => 'image/png'], 'PNG')])->probe(Url::fromString('https://example.com/image'), true);

        self::assertEquals(new ProbeResult(new LinkResult(LinkStatus::Alive, 200)), $result);
    }

    public function testAPageCutAtTheLimitsSaysSo(): void
    {
        $result = $this->probe([$this->answer(200, ['Content-Type' => 'text/html'], str_repeat('a', 2000))], new Limits(maxBytes: 1024))->probe(Url::fromString('https://example.com/page'), true);

        self::assertFalse($result->complete);
        self::assertSame(str_repeat('a', 1024), $result->html);
    }

    public function testResetResetsTheThrottle(): void
    {
        $probe = $this->probe([]);
        $probe->reset();

        self::assertSame(1, $this->throttle->resets);

        $plain = new HttpLinkProbe(new MockHttpClient(), throttle: new class implements HostThrottle {
            public function wait(string $host): void
            {
            }
        });
        $plain->reset();
        self::assertSame(1, $this->throttle->resets);
    }

    public function testResetResetsTheTransport(): void
    {
        $inner = new class implements HttpClientInterface, ResetInterface {
            public int $resets = 0;

            /**
             * @param array<mixed> $options
             */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                throw new LogicException('No request.');
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new LogicException('No stream.');
            }

            /**
             * @param array<mixed> $options
             */
            public function withOptions(array $options): static
            {
                return $this;
            }

            public function reset(): void
            {
                ++$this->resets;
            }
        };

        new HttpLinkProbe($inner, throttle: $this->throttle)->reset();

        self::assertSame(1, $inner->resets);
    }

    public function testTheDefaultsNeedNoArgument(): void
    {
        self::assertSame('PhilLinkChecker/0.1', HttpLinkProbe::USER_AGENT);
        $probe = new HttpLinkProbe();
        $probe->reset();
        self::assertInstanceOf(HttpLinkProbe::class, $probe);
    }
}
