<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use ArrayObject;
use Closure;
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
use Symfony\Contracts\HttpClient\ResponseInterface;
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
        'mapped.example' => '::ffff:10.0.0.1',
        'sixtofour.example' => '2002:a00:1::1',
        'teredo.example' => '2001:0:4136:e378:8000:63bf:f5ff:fffe',
        'nat64.example' => '64:ff9b::a00:1',
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

    /**
     * @param int $headers the headers after the status line, each `X-<n>: v`
     *
     * @return list<string>
     */
    private static function headerLines(int $headers): array
    {
        $lines = ['HTTP/1.1 100 Continue', 'HTTP/1.1 200 OK'];

        for ($i = 0; $i < $headers; ++$i) {
            $lines[] = 'X-'.$i.': v';
        }

        return $lines;
    }

    public function testTheHeadersAreBoundedInCount(): void
    {
        $limits = new Limits(maxHeaders: 100);
        $whole = $this->client([new MockResponse('ok', ['response_headers' => self::headerLines(100)])], $limits)->get('https://example.com/');
        self::assertSame(['ok', 'v'], [$whole->body, $whole->header('X-99')], 'Exactly the headers allowed, the status lines not counted, are read.');

        try {
            $this->client([new MockResponse('ok', ['response_headers' => self::headerLines(101)])], $limits)->get('https://example.com/many');
            self::fail('Too many headers are refused.');
        } catch (FetchFailed $fetchFailed) {
            self::assertSame('"https://example.com/many" sent headers past 65536 bytes or 100 headers.', $fetchFailed->getMessage());
        }
    }

    public function testTheHeadersAreBoundedInBytes(): void
    {
        $limits = new Limits(maxHeaderBytes: 1024);
        // The status line, 15 bytes, counts with the header: `X-Big: ` and its value fill the rest.
        $exact = ['HTTP/1.1 200 OK', 'X-Big: '.str_repeat('a', 1024 - 15 - 7)];
        $whole = $this->client([new MockResponse('ok', ['response_headers' => $exact])], $limits)->get('https://example.com/');
        self::assertSame('ok', $whole->body, 'Exactly the bytes allowed are read.');

        try {
            $this->client([new MockResponse('ok', ['response_headers' => [$exact[0], $exact[1].'a']])], $limits)->head('https://example.com/big');
            self::fail('Headers past their bytes are refused.');
        } catch (FetchFailed $fetchFailed) {
            self::assertSame('"https://example.com/big" sent headers past 1024 bytes or 100 headers.', $fetchFailed->getMessage());
        }
    }

    /**
     * A transport that reports no progress still has the headers counted once they are all in.
     */
    public function testTheHeadersAreCountedOnceAllInWithoutProgress(): void
    {
        $inner = new class(new MockHttpClient([new MockResponse('ok', ['response_headers' => self::headerLines(101)])])) implements HttpClientInterface {
            use DecoratorTrait;

            /**
             * @param array<array-key, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                unset($options['on_progress']);

                return $this->client->request($method, $url, $options);
            }
        };

        $this->expectException(FetchFailed::class);
        $this->expectExceptionMessageIsOrContains('sent headers past 65536 bytes or 100 headers.');
        new SafeHttpClient($inner, new Limits(), self::RESOLVE)->get('https://example.com/');
    }

    /**
     * The progress of the transport counts the headers as they arrive, before they are all in.
     */
    public function testTheHeadersAreCountedAsTheyArrive(): void
    {
        /** @var ArrayObject<int, Closure> $progress */
        $progress = new ArrayObject();
        $client = $this->client(static function (string $method, string $url, array $options) use ($progress): MockResponse {
            $onProgress = $options['on_progress'] ?? null;
            if (!\is_callable($onProgress)) {
                throw new LogicException('The client hands a progress to the transport.');
            }

            $progress[] = $onProgress(...);

            return new MockResponse('ok');
        }, new Limits(maxHeaders: 2));
        $client->get('https://example.com/');

        $onProgress = $progress[0] ?? null;
        self::assertNotNull($onProgress);

        $onProgress(0, 0, ['url' => 'https://example.com/']);
        $onProgress(0, 0, ['url' => 'https://example.com/', 'response_headers' => 'not a list']);
        $onProgress(0, 0, ['url' => 'https://example.com/', 'response_headers' => ['HTTP/1.1 200 OK', 7, null, 'A: 1', 'B: 2']]);

        $this->expectException(FetchFailed::class);
        $this->expectExceptionMessageIsOrContains('"https://example.com/" sent headers past 65536 bytes or 2 headers.');
        // A line the transport did not give as a string is passed over, the lines after it still counted.
        $onProgress(0, 0, ['url' => 'https://example.com/', 'response_headers' => ['HTTP/1.1 200 OK', 7, 'A: 1', 'B: 2', 'C: 3']]);
    }

    /**
     * Thrown by the progress while the transport runs, the refusal comes back wrapped in the transport's error: it is
     * handed on as it was thrown.
     */
    public function testARefusalThrownDuringTheTransferIsHandedOnAsItIs(): void
    {
        $client = $this->client(static function (string $method, string $url, array $options): MockResponse {
            $onProgress = $options['on_progress'] ?? null;
            if (!\is_callable($onProgress)) {
                throw new LogicException('The client hands a progress to the transport.');
            }

            $body = static function () use ($onProgress): Generator {
                yield 'a';
                $onProgress(1, 0, ['url' => 'https://example.com/', 'response_headers' => self::headerLines(101)]);

                yield 'b';
            };

            return new MockResponse($body());
        });

        try {
            $client->get('https://example.com/');
            self::fail('The headers are refused.');
        } catch (FetchFailed $fetchFailed) {
            self::assertSame('"https://example.com/" sent headers past 65536 bytes or 100 headers.', $fetchFailed->getMessage());
            self::assertNull($fetchFailed->getPrevious());
        }
    }

    /**
     * A chunk larger than the bytes that remain is cut before it joins the body: the body never holds more than the
     * limits' bytes, whatever the size of the chunk the transport hands over.
     */
    public function testAChunkLargerThanTheBytesLeftIsCutBeforeItJoinsTheBody(): void
    {
        $limit = 1024 * 1024;
        $huge = 'H'.str_repeat('h', 16 * $limit);

        $single = $this->client([new MockResponse([$huge])], new Limits(maxBytes: $limit))->get('https://example.com/');
        self::assertSame(['H'.str_repeat('h', $limit - 1), false], [$single->body, $single->complete], 'The chunk is kept from its start.');

        $client = $this->client([new MockResponse(['a', $huge])], new Limits(maxBytes: $limit));
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $response = $client->get('https://example.com/');
        $peak = memory_get_peak_usage() - $before;

        self::assertSame(['aH'.str_repeat('h', $limit - 2), false], [$response->body, $response->complete]);
        self::assertLessThan(4 * $limit, $peak, 'The peak stays near the bytes allowed, far below the chunk received.');
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
        yield 'a private network in an IPv4-mapped IPv6 address' => ['https://mapped.example/'];
        yield 'a private network in a 6to4 IPv6 address' => ['https://sixtofour.example/'];
        yield 'a private network in a Teredo IPv6 address' => ['https://teredo.example/'];
        yield 'a private network in a NAT64 IPv6 address' => ['https://nat64.example/'];
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

    /**
     * A response the client does not read to its end — a redirect, a body of another type, a body past the limit —
     * is cancelled, so its connection does not keep downloading.
     */
    public function testAResponseThatIsNotReadToItsEndIsCancelled(): void
    {
        $cancelled = static function (array $responses, Limits $limits = new Limits(), ReadRule $rule = new ReadRule()): array {
            $inner = new class(new MockHttpClient($responses)) implements HttpClientInterface {
                use DecoratorTrait;

                /** @var list<ResponseInterface> */
                public array $sent = [];

                /**
                 * @param array<array-key, mixed> $options
                 */
                public function request(string $method, string $url, array $options = []): ResponseInterface
                {
                    return $this->sent[] = $this->client->request($method, $url, $options);
                }
            };
            new SafeHttpClient($inner, $limits, self::RESOLVE)->get('https://example.com/', $rule);

            return array_map(static fn (ResponseInterface $response): mixed => $response->getInfo('canceled'), $inner->sent);
        };

        self::assertSame([true, false], $cancelled([new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => '/moved']]), new MockResponse('done')]));
        self::assertSame([true], $cancelled([new MockResponse('PNG', ['response_headers' => ['Content-Type' => 'image/png']])], rule: ReadRule::htmlHead()));
        self::assertSame([true], $cancelled([new MockResponse([str_repeat('a', 3000), str_repeat('b', 3000)])], new Limits(maxBytes: 4000)));
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
