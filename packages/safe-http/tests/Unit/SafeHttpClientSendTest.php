<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use ArrayObject;
use Phil\SafeHttp\Exception\InvalidRequest;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\RefusedRedirect;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SafeHttpClient::class)]
#[CoversClass(InvalidRequest::class)]
#[CoversClass(RefusedRedirect::class)]
final class SafeHttpClientSendTest extends TestCase
{
    /** Public addresses of the documentation range, pinned so that no test resolves a name. */
    private const array RESOLVE = [
        'example.com' => '93.184.215.14',
        'www.example.com' => '93.184.215.14',
        'example.org' => '93.184.215.15',
        'internal.example' => '10.0.0.7',
    ];

    /** @var ArrayObject<int, array{method: string, url: string, headers: list<string>, body: string}> */
    private ArrayObject $sent;

    protected function setUp(): void
    {
        $this->sent = new ArrayObject();
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses, Limits $limits = new Limits()): SafeHttpClient
    {
        $sent = $this->sent;

        return new SafeHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use ($sent, &$responses): MockResponse {
            $headers = $options['headers'] ?? [];
            $body = $options['body'] ?? '';
            $sent[] = [
                'method' => $method,
                'url' => $url,
                'headers' => \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : [],
                'body' => \is_string($body) ? $body : '',
            ];

            return array_shift($responses) ?? new MockResponse('');
        }), $limits, self::RESOLVE);
    }

    /**
     * @return list<string>
     */
    private function headersSent(int $request): array
    {
        return ($this->sent[$request] ?? null)['headers'] ?? [];
    }

    public function testAPostCarriesItsHeadersAndBody(): void
    {
        $response = $this->client([new MockResponse('{"access_token":"t"}', ['http_code' => 200, 'response_headers' => ['Content-Type' => 'application/json']])], new Limits(userAgent: 'Test/1.0'))
            ->send('post', 'https://example.com/token', ['Authorization' => 'Basic czY6Z1g=', 'Content-Type' => 'application/x-www-form-urlencoded'], 'grant_type=client_credentials', new ReadRule('application/json'));

        self::assertSame([200, '{"access_token":"t"}', true], [$response->status, $response->body, $response->complete]);
        $request = $this->sent[0] ?? null;
        self::assertNotNull($request);
        self::assertSame(['POST', 'https://example.com/token', 'grant_type=client_credentials'], [$request['method'], $request['url'], $request['body']]);
        self::assertContains('Authorization: Basic czY6Z1g=', $request['headers']);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', $request['headers']);
        self::assertContains('Accept: application/json', $request['headers']);
        self::assertContains('User-Agent: Test/1.0', $request['headers']);
    }

    public function testEveryMethodIsSentInCapitals(): void
    {
        foreach (['GET', 'head', 'Options', 'PUT', 'PATCH', 'DELETE', 'PROPFIND', 'A'] as $i => $method) {
            $this->client([new MockResponse('')])->send($method, Url::fromString('https://example.com/'));

            self::assertSame(strtoupper($method), ($this->sent[$i] ?? null)['method'] ?? null);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedMethods(): iterable
    {
        yield 'nothing' => [''];
        yield 'a tunnel' => ['CONNECT'];
        yield 'an echo' => ['trace'];
        yield 'a space' => ['GET '];
        yield 'a line break' => ["GET\n"];
        yield 'a digit first' => ['1GET'];
        yield 'a slash' => ['GET/1'];
        yield 'a hyphen' => ['X-CUSTOM'];
        yield 'a digit' => ['GET1'];
        yield 'an underscore' => ['M_SEARCH'];
        yield 'too long' => [str_repeat('A', 21)];
    }

    #[DataProvider('refusedMethods')]
    public function testAMethodThatIsNoTokenIsRefusedBeforeAnyRequest(string $method): void
    {
        try {
            $this->client([])->send($method, 'https://example.com/');
            self::fail('The method was sent.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame(\sprintf('"%s" is not a method the client sends.', mb_substr(strtoupper($method), 0, 30)), $invalidRequest->getMessage());
        }

        self::assertCount(0, $this->sent);
    }

    public function testAMethodOfTwentyCharactersIsSent(): void
    {
        $this->client([new MockResponse('')])->send(str_repeat('A', 20), 'https://example.com/');

        self::assertSame(str_repeat('A', 20), ($this->sent[0] ?? null)['method'] ?? null);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function refusedHeaders(): iterable
    {
        yield 'a space in the name' => [['X Token' => 'a'], '"X Token" is not a header name.'];
        yield 'a colon in the name' => [['X-Token:' => 'a'], '"X-Token:" is not a header name.'];
        yield 'an empty name' => [['' => 'a'], '"" is not a header name.'];
        yield 'a line break in the name' => [["X-A\r\nHost" => 'a'], 'is not a header name.'];
        yield 'a line feed after the name' => [["X-A\n" => 'a'], 'is not a header name.'];
        yield 'a name too long' => [[str_repeat('x', 257) => 'a'], 'is not a header name.'];
        yield 'Host' => [['host' => 'internal.example'], 'The host header is set by the client or the transport, never by the request.'];
        yield 'Accept' => [['Accept' => 'text/html'], 'The Accept header is set'];
        yield 'User-Agent' => [['User-Agent' => 'x'], 'The User-Agent header is set'];
        yield 'Content-Length' => [['Content-Length' => '3'], 'The Content-Length header is set'];
        yield 'Transfer-Encoding' => [['Transfer-Encoding' => 'chunked'], 'The Transfer-Encoding header is set'];
        yield 'Connection' => [['Connection' => 'close'], 'The Connection header is set'];
        yield 'Keep-Alive' => [['Keep-Alive' => '5'], 'The Keep-Alive header is set'];
        yield 'Upgrade' => [['Upgrade' => 'h2c'], 'The Upgrade header is set'];
        yield 'TE' => [['TE' => 'trailers'], 'The TE header is set'];
        yield 'Trailer' => [['Trailer' => 'x'], 'The Trailer header is set'];
        yield 'Expect' => [['Expect' => '100-continue'], 'The Expect header is set'];
        yield 'a proxy header' => [['Proxy-Authorization' => 'Basic x'], 'The Proxy-Authorization header is set'];
        yield 'an empty value' => [['X-Token' => ''], 'The value of the X-Token header is empty, or holds a line break or a control character.'];
        yield 'a value of spaces' => [['X-Token' => '  '], 'The value of the X-Token header is empty'];
        yield 'a line break in the value' => [['X-Token' => "a\r\nHost: internal.example"], 'The value of the X-Token header is empty'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('refusedHeaders')]
    public function testAHeaderIsCheckedBeforeAnyRequest(array $headers, string $message): void
    {
        try {
            $this->client([])->send('POST', 'https://example.com/', ['X-Fine' => 'ok', ...$headers]);
            self::fail('The header was sent.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertStringContainsString($message, $invalidRequest->getMessage());
            self::assertStringNotContainsString('internal.example', $invalidRequest->getMessage(), 'A message names no value.');
        }

        self::assertCount(0, $this->sent);
    }

    public function testAHeaderNamedLikeAProxyHeaderIsSent(): void
    {
        $this->client([new MockResponse('')])->send('GET', 'https://example.com/', [str_repeat('x', 256) => 'f', 'Proxied-By' => 'a', 'X-Proxy-Id' => 'b', 'Hosting' => 'c', 'Accepted' => 'd', "!#$%&'*+.^_`|~09az-" => 'e']);

        self::assertContains('Proxied-By: a', $this->headersSent(0));
        self::assertContains('X-Proxy-Id: b', $this->headersSent(0));
        self::assertContains('Hosting: c', $this->headersSent(0));
        self::assertContains('Accepted: d', $this->headersSent(0));
    }

    public function testAHeaderNamedByDigitsIsRefusedBeforeAnyRequest(): void
    {
        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('"123" is not a header name.');

        try {
            // An integer key: Symfony HttpClient would send its value as a header line of its own.
            $this->client([new MockResponse('')])->send('GET', 'https://example.com/', ['123' => 'Host: internal.example']);
        } finally {
            self::assertCount(0, $this->sent);
        }
    }

    public function testTheHeadersAreBoundedInCount(): void
    {
        $limits = new Limits(maxHeaders: 3);
        $three = ['A' => '1', 'B' => '2', 'C' => '3'];
        $this->client([new MockResponse('')], $limits)->send('GET', 'https://example.com/', $three);
        self::assertCount(1, $this->sent);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('The headers of the request pass 65536 bytes or 3 headers.');

        $this->client([], $limits)->send('GET', 'https://example.com/', [...$three, 'D' => '4']);
    }

    public function testTheHeadersAreBoundedInBytes(): void
    {
        $limits = new Limits(maxHeaderBytes: 1024);
        // "X: " + 1021 bytes = 1024: the line, its colon and its space counted.
        $this->client([new MockResponse('')], $limits)->send('GET', 'https://example.com/', ['X' => str_repeat('a', 1021)]);
        self::assertCount(1, $this->sent);

        // One byte past the limit is refused: the count starts at 0.
        try {
            $this->client([], $limits)->send('GET', 'https://example.com/', ['X' => str_repeat('a', 1022)]);
            self::fail('Headers past the bytes were sent.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame('The headers of the request pass 1024 bytes or 100 headers.', $invalidRequest->getMessage());
        }

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('The headers of the request pass 1024 bytes or 100 headers.');

        $this->client([], $limits)->send('GET', 'https://example.com/', ['X' => str_repeat('a', 1010), 'Y' => str_repeat('b', 10)]);
    }

    public function testTheBodyIsBoundedByTheBytesOfTheLimits(): void
    {
        $limits = new Limits(maxBytes: 1024);
        $this->client([new MockResponse('')], $limits)->send('PUT', 'https://example.com/', [], str_repeat('a', 1024));
        self::assertSame(1024, \strlen(($this->sent[0] ?? null)['body'] ?? ''));

        try {
            $this->client([], $limits)->send('PUT', 'https://example.com/', [], str_repeat('a', 1025));
            self::fail('A body past the bytes was sent.');
        } catch (InvalidRequest $invalidRequest) {
            self::assertSame('The body of the request is 1025 bytes; 1024 at most.', $invalidRequest->getMessage());
        }

        self::assertCount(1, $this->sent);
    }

    public function testAnAddressThatIsNotPublicIsRefusedBeforeAnyRequest(): void
    {
        foreach (['http://10.0.0.1/', 'http://localhost/', 'https://ada@example.com/'] as $url) {
            try {
                $this->client([])->send('POST', $url, ['Authorization' => 'Bearer t'], 'a=1');
                self::fail($url.' was sent.');
            } catch (RefusedAddress) {
            }
        }

        self::assertCount(0, $this->sent);
    }

    public function testAHostThatResolvesToAPrivateNetworkIsRefused(): void
    {
        $this->expectException(RefusedAddress::class);

        $this->client([new MockResponse('')])->send('POST', 'https://internal.example/', [], 'a=1');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notFollowing(): iterable
    {
        yield 'a POST' => ['POST', ''];
        yield 'a PUT' => ['PUT', ''];
        yield 'a DELETE' => ['DELETE', ''];
        yield 'a PATCH with a body' => ['PATCH', '{}'];
        yield 'a GET with a body' => ['GET', 'q=1'];
        yield 'an OPTIONS with a body' => ['OPTIONS', 'q=1'];
    }

    #[DataProvider('notFollowing')]
    public function testARedirectIsRefusedToARequestWithABodyOrAnotherMethod(string $method, string $body): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $this->sent->exchangeArray([]);

            try {
                $this->client([new MockResponse('', ['http_code' => $status, 'response_headers' => ['Location' => '/again']])])->send($method, 'https://example.com/start', [], $body);
                self::fail('The redirect was followed.');
            } catch (RefusedRedirect $refusedRedirect) {
                self::assertSame(\sprintf('"https://example.com/start" answered %s with the redirect %d, which a request with a body or a method other than GET, HEAD or OPTIONS does not follow.', $method, $status), $refusedRedirect->getMessage());
            }

            self::assertCount(1, $this->sent);
        }
    }

    public function testAnAnswerOfAnotherStatusIsTheAnswerOfAPost(): void
    {
        $response = $this->client([new MockResponse('', ['http_code' => 304, 'response_headers' => ['Location' => '/again']])])->send('POST', 'https://example.com/', [], 'a=1');

        self::assertSame(304, $response->status);
    }

    public function testARequestWithoutBodyOfAFollowingMethodFollowsTheRedirects(): void
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $i => $method) {
            $response = $this->client([new MockResponse('', ['http_code' => 307, 'response_headers' => ['Location' => '/moved']]), new MockResponse('ok')])->send($method, 'https://example.com/');

            self::assertSame('https://example.com/moved', $response->url->toString(), $method);
            self::assertSame($method, ($this->sent[2 * $i + 1] ?? null)['method'] ?? null);
        }
    }

    public function testTheHeadersStayOnTheOriginOfTheFirstAddress(): void
    {
        $responses = [
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/same']]),
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.org/other']]),
            new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://example.com/back']]),
            new MockResponse('done'),
        ];

        $response = $this->client($responses)->send('GET', 'https://example.com/', ['Authorization' => 'Bearer secret', 'Cookie' => 'session=1', 'X-Api-Key' => 'k']);

        self::assertSame('https://example.com/back', $response->url->toString());

        foreach ([0 => true, 1 => true, 2 => false, 3 => true] as $request => $carried) {
            $headers = implode("\n", $this->headersSent($request));
            self::assertSame($carried, str_contains($headers, 'Bearer secret'), (string) $request);
            self::assertSame($carried, str_contains($headers, 'session=1'), (string) $request);
            self::assertSame($carried, str_contains($headers, 'X-Api-Key: k'), (string) $request);
            self::assertStringContainsString('User-Agent: PhilSafeHttp/0.1', $headers);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherOrigins(): iterable
    {
        yield 'another port' => ['https://example.com:8443/'];
        yield 'a subdomain' => ['https://www.example.com/'];
    }

    #[DataProvider('otherOrigins')]
    public function testARedirectToAnotherSchemeOrPortCarriesNoneOfTheHeaders(string $location): void
    {
        $this->client([new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => $location]]), new MockResponse('')])
            ->send('GET', 'https://example.com/', ['Authorization' => 'Bearer secret']);

        self::assertContains('Authorization: Bearer secret', $this->headersSent(0));
        self::assertNotContains('Authorization: Bearer secret', $this->headersSent(1));
    }

    public function testAnUpgradeToHttpsCarriesNoneOfTheHeaders(): void
    {
        $response = $this->client([new MockResponse('', ['http_code' => 308, 'response_headers' => ['Location' => 'https://example.com/']]), new MockResponse('ok')])
            ->send('GET', 'http://example.com/', ['Authorization' => 'Bearer secret']);

        self::assertSame('https://example.com/', $response->url->toString());
        self::assertContains('Authorization: Bearer secret', $this->headersSent(0));
        self::assertNotContains('Authorization: Bearer secret', $this->headersSent(1));
    }

    public function testADowngradeToHttpIsRefusedAndTheHeadersNeverSentInClearText(): void
    {
        foreach ([301, 302, 303, 307, 308] as $status) {
            $this->sent->exchangeArray([]);

            try {
                $this->client([new MockResponse('', ['http_code' => $status, 'response_headers' => ['Location' => 'http://example.com/']]), new MockResponse('')])
                    ->send('GET', 'https://example.com/', ['Authorization' => 'Bearer secret']);
                self::fail(\sprintf('The %d downgrade is refused.', $status));
            } catch (RefusedAddress) {
                self::assertCount(1, $this->sent, (string) $status);
            }
        }
    }

    public function testTheRedirectsOfSendAreBounded(): void
    {
        $loop = array_fill(0, 3, new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => '/again']]));

        $this->expectException(TooManyRedirects::class);

        $this->client($loop, new Limits(maxRedirects: 2))->send('GET', 'https://example.com/');
    }

    public function testAHeadSentReadsNoBody(): void
    {
        $response = $this->client([new MockResponse('body')])->send('HEAD', 'https://example.com/');

        self::assertSame(['', false], [$response->body, $response->complete]);
    }
}
