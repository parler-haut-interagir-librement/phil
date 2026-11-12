<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use ArrayObject;
use Generator;
use LogicException;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SafeHttp\SafeResponse;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(SafeHttpClient::class)]
#[CoversClass(SafeResponse::class)]
#[CoversClass(RefusedAddress::class)]
#[CoversClass(TooManyRedirects::class)]
#[CoversClass(FetchFailed::class)]
final class SafeHttpClientTest extends TestCase
{
    /** Public addresses of the documentation range, pinned so that no test resolves a name. */
    private const array RESOLVE = [
        'example.com' => '93.184.215.14',
        'www.example.com' => '93.184.215.14',
        'example.org' => '93.184.215.15',
        'internal.example' => '10.0.0.7',
        'metadata.example' => '169.254.169.254',
        'loopback.example' => '127.0.0.1',
        'v6.example' => '::1',
    ];

    /**
     * @param list<MockResponse>|callable(string, string, array<array-key, mixed>): MockResponse $responses
     */
    private function client(array|callable $responses, Limits $limits = new Limits()): SafeHttpClient
    {
        return new SafeHttpClient(new MockHttpClient($responses), $limits, self::RESOLVE);
    }

    public function testABodyIsReadWithTheLimitsInTheRequest(): void
    {
        /** @var ArrayObject<int, array{method: string, url: string, options: array<array-key, mixed>}> $sent */
        $sent = new ArrayObject();
        $client = $this->client(static function (string $method, string $url, array $options) use ($sent): MockResponse {
            $sent[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse(['{"a":', '1}'], ['response_headers' => ['Content-Type' => 'Application/JSON; charset="UTF-8"', 'X-Two' => ['a', 'b']]]);
        }, new Limits(2.5, 7.5, 4096, 2, 'Test/1.0 (+https://example.com/bot)'));

        $response = $client->get('HTTPS://Example.com/feed?x=1', new ReadRule('application/json'));

        self::assertSame(['https://example.com/feed?x=1', 200, '{"a":1}', true, []], [$response->url->toString(), $response->status, $response->body, $response->complete, $response->redirects]);
        self::assertSame(['application/json', 'UTF-8', 'b', null], [$response->mediaType(), $response->charset(), $response->headers['x-two'][1] ?? null, $response->header('X-None')]);
        self::assertSame('Application/JSON; charset="UTF-8"', $response->header('CONTENT-TYPE'));
        self::assertTrue($response->isSuccessful());
        $request = $sent[0] ?? null;
        self::assertNotNull($request);
        $options = $request['options'];
        self::assertSame(['GET', 'https://example.com/feed?x=1', 2.5, 0, false], [$request['method'], $request['url'], $options['timeout'] ?? null, $options['max_redirects'] ?? null, $options['buffer'] ?? null]);
        self::assertIsFloat($options['max_duration'] ?? null);
        self::assertGreaterThan(7.0, $options['max_duration']);
        self::assertLessThanOrEqual(7.5, $options['max_duration']);
        self::assertIsArray($options['headers'] ?? null);
        self::assertContains('User-Agent: Test/1.0 (+https://example.com/bot)', $options['headers']);
        self::assertContains('Accept: application/json', $options['headers']);
    }

    public function testTheTimeoutNeverExceedsWhatRemainsOfTheWholeDuration(): void
    {
        /** @var ArrayObject<int, float> $timeouts */
        $timeouts = new ArrayObject();
        $client = $this->client(static function (string $method, string $url, array $options) use ($timeouts): MockResponse {
            $timeouts[] = \is_float($options['timeout'] ?? null) ? $options['timeout'] : -1.0;

            return new MockResponse('x');
        }, new Limits(timeout: 30.0, maxDuration: 2.0));

        $client->get(Url::fromString('https://example.com/'));

        self::assertLessThanOrEqual(2.0, $timeouts[0] ?? 99.0);
        self::assertGreaterThan(1.5, $timeouts[0] ?? 0.0);
    }

    public function testTheBodyStopsAtTheBytesOfTheLimits(): void
    {
        $cut = $this->client([new MockResponse([str_repeat('a', 3000), str_repeat('b', 3000), str_repeat('c', 3000)])], new Limits(maxBytes: 4000))->get('https://example.com/');
        self::assertSame([str_repeat('a', 3000).str_repeat('b', 1000), false], [$cut->body, $cut->complete]);

        $exact = $this->client([new MockResponse([str_repeat('a', 2000), str_repeat('b', 2000)])], new Limits(maxBytes: 4000))->get('https://example.com/');
        self::assertSame([4000, true], [\strlen($exact->body), $exact->complete], 'A body of exactly the bytes allowed is whole.');

        $over = $this->client([new MockResponse([str_repeat('a', 4000), 'b'])], new Limits(maxBytes: 4000))->get('https://example.com/');
        self::assertSame([str_repeat('a', 4000), false], [$over->body, $over->complete]);
    }

    public function testTheBodyStopsAfterTheMark(): void
    {
        $rule = ReadRule::htmlHead();
        $page = $this->client([new MockResponse(['<html><head><title>T</title>', '<meta property="og:title" content="X"></HEAD>', '<body>'.str_repeat('x', 100).'</body>'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', $rule);
        self::assertSame(['<html><head><title>T</title><meta property="og:title" content="X"></HEAD>', false], [$page->body, $page->complete]);

        $split = $this->client([new MockResponse(['<head><title>T</title></he', 'ad><body>', 'more'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', $rule);
        self::assertSame('<head><title>T</title></head><body>', $split->body, 'A mark split between two chunks is found.');

        $nearly = $this->client([new MockResponse(['<head><title>T</title></hea', 'd><body>', 'more'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', $rule);
        self::assertSame('<head><title>T</title></head><body>', $nearly->body, 'A mark of which one character comes in the next chunk is found.');

        $whole = $this->client([new MockResponse(['<head></head>'.str_repeat('x', 100), 'more'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', $rule);
        self::assertSame(['<head></head>'.str_repeat('x', 100), false], [$whole->body, $whole->complete], 'A mark far from the end of the first chunk is found.');

        $none = $this->client([new MockResponse(['<p>no head</p>', '<p>at all</p>'], ['response_headers' => ['Content-Type' => 'application/xhtml+xml']])])->get('https://example.com/', $rule);
        self::assertSame(['<p>no head</p><p>at all</p>', true], [$none->body, $none->complete]);
    }

    public function testAMarkAtTheStartOfTheBodyIsFound(): void
    {
        $page = $this->client([new MockResponse(['</head>', 'after'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', ReadRule::htmlHead());

        self::assertSame(['</head>', false], [$page->body, $page->complete]);
    }

    public function testEveryChunkAfterTheMarkIsDropped(): void
    {
        $page = $this->client([new MockResponse(['<head></head>', 'one', 'two'], ['response_headers' => ['Content-Type' => 'text/html']])])->get('https://example.com/', ReadRule::htmlHead());

        self::assertSame(['<head></head>', false], [$page->body, $page->complete]);
    }

    public function testTheRedirectsAreCountedBeforeTheyAreFollowed(): void
    {
        /** @var ArrayObject<int, string> $urls */
        $urls = new ArrayObject();
        $client = $this->client(static function (string $method, string $url) use ($urls): MockResponse {
            $urls[] = $url;

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/again']]);
        }, new Limits(maxRedirects: 2));

        try {
            $client->get('https://example.com/start');
            self::fail('A third redirect is refused.');
        } catch (TooManyRedirects) {
            self::assertCount(3, $urls, 'The first request and two redirects, no more.');
        }
    }

    public function testABlockedHostIsOnlyRecognisedByTheStartOfTheMessage(): void
    {
        $this->expectException(FetchFailed::class);
        $this->client([new MockResponse('', ['error' => 'The proxy said: Host "internal.example" is blocked for security reasons.'])])->get('https://example.com/');
    }

    public function testTheCharsetIsReadWhateverTheCase(): void
    {
        $response = $this->client([new MockResponse('x', ['response_headers' => ['Content-Type' => 'text/html; CHARSET=ISO-8859-1']])])->get('https://example.com/');

        self::assertSame('ISO-8859-1', $response->charset());
    }

    public function testAMarkInAnEarlierChunkDoesNotStopAgain(): void
    {
        $rule = new ReadRule(stopAfter: 'abc');
        $page = $this->client([new MockResponse(['xxab', 'cyy', 'zz'])])->get('https://example.com/', $rule);

        self::assertSame('xxabcyy', $page->body);
    }

    public function testABodyOfAnotherTypeIsNotRead(): void
    {
        $image = $this->client([new MockResponse('PNG…', ['response_headers' => ['Content-Type' => 'image/png']])])->get('https://example.com/', ReadRule::htmlHead());
        self::assertSame(['', false, 'image/png'], [$image->body, $image->complete, $image->mediaType()]);

        $untyped = $this->client([new MockResponse('?')])->get('https://example.com/', ReadRule::htmlHead());
        self::assertSame(['', null], [$untyped->body, $untyped->mediaType()]);
    }

    public function testAnErrorStatusIsAnAnswer(): void
    {
        $response = $this->client([new MockResponse('gone', ['http_code' => 404])])->get('https://example.com/');

        self::assertSame([404, 'gone', false], [$response->status, $response->body, $response->isSuccessful()]);
        self::assertFalse(new SafeResponse(Url::fromString('https://example.com/'), 199, [], '', true)->isSuccessful());
        self::assertFalse(new SafeResponse(Url::fromString('https://example.com/'), 300, [], '', true)->isSuccessful());
        self::assertTrue(new SafeResponse(Url::fromString('https://example.com/'), 299, [], '', true)->isSuccessful());
    }

    public function testAHeadReadsNoBody(): void
    {
        /** @var ArrayObject<int, string> $methods */
        $methods = new ArrayObject();
        $client = $this->client(static function (string $method) use ($methods): MockResponse {
            $methods[] = $method;

            return new MockResponse('body', ['response_headers' => ['Content-Length' => '4']]);
        });

        $response = $client->head('https://example.com/');

        self::assertSame(['HEAD', '', false, '4'], [$methods[0] ?? null, $response->body, $response->complete, $response->header('content-length')]);
    }

    public function testTheRedirectsAreFollowedAndChecked(): void
    {
        /** @var ArrayObject<int, string> $urls */
        $urls = new ArrayObject();
        $responses = [
            new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/moved']]),
            new MockResponse('', ['http_code' => 308, 'response_headers' => ['Location' => 'https://www.example.com/final']]),
            new MockResponse('done', ['response_headers' => ['Content-Type' => 'text/plain']]),
        ];
        $client = $this->client(static function (string $method, string $url) use ($urls, &$responses): MockResponse {
            $urls[] = $url;

            return array_shift($responses) ?? new MockResponse('');
        }, new Limits(maxRedirects: 2));

        $response = $client->get('https://example.com/start');

        self::assertSame(['https://example.com/start', 'https://example.com/moved', 'https://www.example.com/final'], $urls->getArrayCopy());
        self::assertSame(['https://www.example.com/final', 'done'], [$response->url->toString(), $response->body]);
        self::assertSame(['https://example.com/moved', 'https://www.example.com/final'], array_map(static fn (Url $url): string => $url->toString(), $response->redirects));
    }

    public function testEachKindOfRedirectIsFollowed(): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $response = $this->client([new MockResponse('', ['http_code' => $status, 'response_headers' => ['Location' => 'https://example.org/']]), new MockResponse('ok')])->get('https://example.com/');

            self::assertSame('https://example.org/', $response->url->toString(), (string) $status);
        }
    }

    public function testARedirectWithoutLocationOrOfAnotherStatusIsTheAnswer(): void
    {
        $bare = $this->client([new MockResponse('', ['http_code' => 302])])->get('https://example.com/');
        self::assertSame([302, []], [$bare->status, $bare->redirects]);

        $notModified = $this->client([new MockResponse('', ['http_code' => 304, 'response_headers' => ['Location' => 'https://example.org/']])])->get('https://example.com/');
        self::assertSame([304, 'https://example.com/'], [$notModified->status, $notModified->url->toString()]);

        $multiple = $this->client([new MockResponse('', ['http_code' => 300, 'response_headers' => ['Location' => 'https://example.org/']])])->get('https://example.com/');
        self::assertSame(300, $multiple->status);
    }

    public function testTheRedirectsAreBounded(): void
    {
        $loop = static fn (): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/again']]);

        try {
            $this->client($loop, new Limits(maxRedirects: 2))->get('https://example.com/start');
            self::fail('A third redirect is refused.');
        } catch (TooManyRedirects $tooManyRedirects) {
            self::assertSame('"https://example.com/start" redirects more than 2 times.', $tooManyRedirects->getMessage());
        }

        $this->expectException(TooManyRedirects::class);
        $this->client($loop, new Limits(maxRedirects: 0))->get('https://example.com/');
    }

    public function testExactlyTheRedirectsAllowedAreFollowed(): void
    {
        $responses = [
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/1']]),
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/2']]),
            new MockResponse('end'),
        ];

        self::assertSame('end', $this->client($responses, new Limits(maxRedirects: 2))->get('https://example.com/')->body);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedRedirects(): iterable
    {
        yield 'an IP address' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'a decimal IP address' => ['http://2130706433/'];
        yield 'another scheme' => ['file:///etc/passwd'];
        yield 'credentials' => ['https://user:pass@example.org/'];
        yield 'nothing' => ['https://'];
    }

    #[DataProvider('refusedRedirects')]
    public function testARedirectToAnAddressThatIsNotPublicIsRefused(string $location): void
    {
        $this->expectException(RefusedAddress::class);

        $this->client([new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => $location]])])->get('https://example.com/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateHosts(): iterable
    {
        yield 'a private network' => ['https://internal.example/'];
        yield 'a cloud metadata endpoint' => ['http://metadata.example/latest/meta-data/'];
        yield 'the loopback' => ['https://loopback.example/'];
        yield 'the IPv6 loopback' => ['https://v6.example/'];
    }

    #[DataProvider('privateHosts')]
    public function testAHostThatResolvesToAPrivateNetworkIsRefused(string $url): void
    {
        try {
            $this->client([new MockResponse('secret')])->get($url);
            self::fail('The host is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame(\sprintf('"%s" is or resolves to a private, loopback, link-local or reserved network.', $url), $refusedAddress->getMessage());
            self::assertInstanceOf(TransportException::class, $refusedAddress->getPrevious());
        }
    }

    public function testARedirectToAPrivateHostIsRefused(): void
    {
        try {
            $this->client([new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://internal.example/admin']]), new MockResponse('secret')])->get('https://example.com/');
            self::fail('The redirect is refused.');
        } catch (RefusedAddress $refusedAddress) {
            self::assertSame('"https://internal.example/admin" is or resolves to a private, loopback, link-local or reserved network.', $refusedAddress->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'an IP address' => ['http://10.0.0.1/'];
        yield 'an IPv6 address' => ['http://[::1]/'];
        yield 'credentials' => ['https://user@example.com/'];
        yield 'another scheme' => ['gopher://example.com/'];
        yield 'relative' => ['/path'];
    }

    #[DataProvider('refusedAddresses')]
    public function testAnAddressThatIsNotPublicIsRefusedBeforeAnyRequest(string $url): void
    {
        $client = $this->client(static fn (): MockResponse => throw new LogicException('No request is sent.'));

        $this->expectException(RefusedAddress::class);
        $client->get($url);
    }

    public function testATransportErrorFails(): void
    {
        try {
            $this->client([new MockResponse('', ['error' => 'Connection refused'])])->get('https://example.com/x');
            self::fail('The read fails.');
        } catch (FetchFailed $fetchFailed) {
            self::assertSame('"https://example.com/x" could not be read: Connection refused', $fetchFailed->getMessage());
            self::assertInstanceOf(TransportException::class, $fetchFailed->getPrevious());
        }
    }

    public function testAnErrorWhileTheBodyIsReadFails(): void
    {
        $body = static function (): Generator {
            yield 'a';

            throw new TransportException('Broken');
        };

        $this->expectException(FetchFailed::class);
        $this->client([new MockResponse($body())])->get('https://example.com/');
    }

    public function testTheWholeDurationCoversTheRedirects(): void
    {
        $client = $this->client(static function (): MockResponse {
            usleep(150_000);

            return new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/next']]);
        }, new Limits(maxDuration: 0.1));

        try {
            $client->get('https://example.com/slow');
            self::fail('The read runs out of time.');
        } catch (FetchFailed $fetchFailed) {
            self::assertSame('"https://example.com/next" took longer than the whole duration allowed.', $fetchFailed->getMessage());
        }
    }

    public function testTheClientIsResetBetweenReads(): void
    {
        $inner = new class(new MockHttpClient()) implements HttpClientInterface, ResetInterface {
            use DecoratorTrait;

            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
            }
        };

        $client = new SafeHttpClient($inner);
        $client->reset();

        self::assertSame(1, $inner->resets);
    }

    public function testTheDefaultClientIsBuilt(): void
    {
        $this->expectException(RefusedAddress::class);

        new SafeHttpClient(resolve: ['example.com' => '127.0.0.1'])->get('https://example.com/');
    }
}
