<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Http;

use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\LinkHeader;
use Phil\Lti\Http\SafeHttpServiceClient;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Http\UrlPath;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(LinkHeader::class)]
#[CoversClass(SafeHttpServiceClient::class)]
#[CoversClass(ServiceResponse::class)]
#[CoversClass(ServiceRequest::class)]
#[CoversClass(UrlPath::class)]
final class HttpTest extends TestCase
{
    private const array RESOLVE = ['lms.example.org' => '93.184.215.14'];

    public function testTheRelationsOfALinkHeaderAreFound(): void
    {
        $base = Url::fromString('https://lms.example.org:8443/c/1/members?p=1');
        $headers = ['<https://lms.example.org/a>; rel="first", </c/1/members?p=2>; title="x"; rel="next last"', '<https://lms.example.org/d>;rel=differences'];

        self::assertSame('https://lms.example.org:8443/c/1/members?p=2', LinkHeader::find($headers, 'next', $base)?->toString());
        self::assertSame('https://lms.example.org:8443/c/1/members?p=2', LinkHeader::find($headers, 'LAST', $base)?->toString());
        self::assertSame('https://lms.example.org/a', LinkHeader::find($headers, 'first', $base)?->toString());
        self::assertSame('https://lms.example.org/d', LinkHeader::find($headers, 'differences', $base)?->toString());
        self::assertNull(LinkHeader::find($headers, 'prev', $base));
        self::assertNull(LinkHeader::find(['<https://lms.example.org/x>; title="next"'], 'next', $base));
        self::assertNull(LinkHeader::find(['<//evil.example.com/x>; rel=next'], 'next', $base));
        self::assertNull(LinkHeader::find(['<javascript:alert(1)>; rel=next'], 'next', $base));
        self::assertNull(LinkHeader::find(['<https://lms.example.org/'.str_repeat('a', LinkHeader::MAX_LENGTH).'>; rel=next'], 'next', $base));
        self::assertSame('https://tool.localhost/x', LinkHeader::find(['<https://tool.localhost/x>; rel=next'], 'next', $base, true)?->toString());
        self::assertSame('https://lms.example.org:8443/n', LinkHeader::find(['<https://lms.example.org/bad host>; rel=next, </n>; rel=next'], 'next', $base)?->toString());
    }

    public function testARequestIsSentWithItsHeadersAndBody(): void
    {
        /** @var array{string, string, list<string>, mixed} $seen */
        $seen = ['', '', [], null];
        $client = $this->client(new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $headers = $options['headers'] ?? [];
            $seen = [$method, $url, \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : [], $options['body'] ?? null];

            return new MockResponse('{"ok":true}', ['http_code' => 201, 'response_headers' => ['Link' => '<https://lms.example.org/n>; rel=next', 'Content-Type' => 'application/json']]);
        }));

        $response = $client->send(new ServiceRequest('POST', Url::fromString('https://lms.example.org/token'), ['Authorization' => 'Bearer t', 'ACCEPT' => 'application/json'], 'a=b'));

        self::assertSame(201, $response->status);
        self::assertSame('{"ok":true}', $response->body);
        self::assertTrue($response->isSuccessful());
        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertSame(['<https://lms.example.org/n>; rel=next'], $response->headers('LINK'));
        self::assertNull($response->header('X-None'));
        self::assertSame([], $response->headers('x-none'));
        self::assertSame(['POST', 'https://lms.example.org/token', 'a=b'], [$seen[0], $seen[1], $seen[3]]);
        self::assertContains('Authorization: Bearer t', $seen[2]);
        self::assertContains('User-Agent: PhilLti/0.1', $seen[2]);
        self::assertContains('Accept: application/json', $seen[2]);
        $client->reset();
    }

    public function testTheBoundsOfASendAreKept(): void
    {
        $cases = [
            'http' => ['http://lms.example.org/token', new MockResponse(''), 'A service of LTI is called over HTTPS; "http://lms.example.org/token" is not.'],
            'large' => ['https://lms.example.org/token', new MockResponse([str_repeat('a', 1024), str_repeat('b', 1025)]), 'The answer of "https://lms.example.org/token" is larger than the limits of the client.'],
            'transport' => ['https://lms.example.org/token', new MockResponse('', ['error' => 'down']), 'The service at "https://lms.example.org/token" cannot be reached: '],
            'redirect' => ['https://lms.example.org/token', new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://lms.example.org/other']]), 'The service at "https://lms.example.org/token" cannot be reached: '],
        ];

        foreach ($cases as $name => [$url, $answer, $message]) {
            try {
                $this->client(new MockHttpClient($answer), 2048)->send(new ServiceRequest('GET', Url::fromString($url)));
                self::fail($name);
            } catch (ServiceFailed $serviceFailed) {
                self::assertStringStartsWith($message, $serviceFailed->getMessage(), $name);
            }
        }

        self::assertSame(2048, \strlen($this->client(new MockHttpClient(new MockResponse([str_repeat('a', 1024), str_repeat('b', 1024)])), 2048)->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x')))->body));
    }

    public function testAPrivateAddressIsRefused(): void
    {
        $this->expectException(ServiceFailed::class);
        new SafeHttpServiceClient(new SafeHttpClient(new MockHttpClient(new MockResponse('')), SafeHttpServiceClient::defaultLimits(), ['lms.example.org' => '10.0.0.1']))->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x')));
    }

    public function testTheDefaultClientIsBuilt(): void
    {
        $this->expectExceptionMessageIsOrContains('over HTTPS');
        new SafeHttpServiceClient()->send(new ServiceRequest('GET', Url::fromString('http://lms.example.org/x')));
    }

    public function testARefusedHeaderIsAServiceFailure(): void
    {
        $this->expectException(ServiceFailed::class);
        $this->expectExceptionMessageIsOrContains('The service at "https://lms.example.org/x" cannot be reached: ');
        $this->client(new MockHttpClient(new MockResponse('')))->send(new ServiceRequest('GET', Url::fromString('https://lms.example.org/x'), ['Accept' => "a\r\nb"]));
    }

    public function testTheStatusesOfSuccess(): void
    {
        self::assertFalse(new ServiceResponse(199, [], '')->isSuccessful());
        self::assertTrue(new ServiceResponse(200, [], '')->isSuccessful());
        self::assertTrue(new ServiceResponse(299, [], '')->isSuccessful());
        self::assertFalse(new ServiceResponse(300, [], '')->isSuccessful());
    }

    public function testASegmentIsAddedToThePath(): void
    {
        self::assertSame('https://lms.example.org:8443/items/9/scores?a=1', UrlPath::append(Url::fromString('https://lms.example.org:8443/items/9/?a=1#x'), 'scores')->toString());
        self::assertSame('https://lms.example.org/scores', UrlPath::append(Url::fromString('https://lms.example.org'), 'scores')->toString());
    }

    private function client(MockHttpClient $http, int $maxBytes = SafeHttpServiceClient::DEFAULT_MAX_BYTES): SafeHttpServiceClient
    {
        return new SafeHttpServiceClient(new SafeHttpClient($http, new Limits(timeout: 5.0, maxDuration: 20.0, maxBytes: $maxBytes, maxRedirects: 0, userAgent: SafeHttpServiceClient::USER_AGENT), self::RESOLVE));
    }
}
