<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Adapter;

use Phil\OpenBadges\Adapter\SafeHttpFetcher;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(SafeHttpFetcher::class)]
final class SafeHttpFetcherTest extends TestCase
{
    private const array RESOLVE = ['issuer.example.org' => '93.184.215.14', 'other.example.org' => '93.184.215.15'];

    /**
     * @var list<array{string, string, list<string>}>
     */
    private array $requests = [];

    public function testADocumentIsReadOverHttpsWithTheMediaTypesOfDids(): void
    {
        $body = '{"id":"did:web:issuer.example.org"}';
        $fetcher = $this->fetcher(new MockResponse($body, ['response_headers' => ['Content-Type' => 'application/did+json']]));

        self::assertSame($body, $fetcher->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json')));
        self::assertSame('GET', $this->requests[0][0]);
        self::assertSame('https://issuer.example.org/.well-known/did.json', $this->requests[0][1]);
        self::assertContains('Accept: application/did+json, application/did+ld+json;q=0.9, application/json;q=0.8', $this->requests[0][2]);
        self::assertSame(SafeHttpFetcher::ACCEPT, 'application/did+json, application/did+ld+json;q=0.9, application/json;q=0.8');
    }

    public function testAnHttpAddressIsNotRead(): void
    {
        $fetcher = $this->fetcher();

        try {
            $fetcher->fetch(Url::fromString('http://issuer.example.org/.well-known/did.json'));
            self::fail('An HTTP address was read.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('A DID document is read over HTTPS; "http://issuer.example.org/.well-known/did.json" is not.', $invalidCredential->getMessage());
        }

        self::assertSame([], $this->requests);
    }

    public function testAnAnswerOtherThan200IsRefused(): void
    {
        foreach ([201, 204, 404, 500] as $status) {
            try {
                $this->fetcher(new MockResponse('{}', ['http_code' => $status]))->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json'));
                self::fail((string) $status);
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame(\sprintf('The DID document at "https://issuer.example.org/.well-known/did.json" answered %d.', $status), $invalidCredential->getMessage());
            }
        }
    }

    public function testADocumentLargerThanTheLimitsIsRefused(): void
    {
        $client = new SafeHttpClient($this->client(new MockResponse(str_repeat(' ', 2048))), new Limits(maxBytes: 1024), self::RESOLVE);

        $this->expectExceptionObject(InvalidCredential::because('The DID document at "https://issuer.example.org/.well-known/did.json" is larger than the limits allow.'));
        new SafeHttpFetcher($client)->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json'));
    }

    public function testARedirectAwayFromHttpsIsRefused(): void
    {
        $fetcher = $this->fetcher(new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://other.example.org/did.json']]), new MockResponse('{}'));

        $this->expectExceptionObject(InvalidCredential::because('The DID document at "https://issuer.example.org/.well-known/did.json" cannot be read: The redirect from "https://issuer.example.org/.well-known/did.json" to "http://other.example.org/did.json" leaves https for http: its headers, body and answer would travel in clear text.'));
        $fetcher->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json'));
    }

    public function testARedirectOverHttpsIsFollowed(): void
    {
        $fetcher = $this->fetcher(new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://other.example.org/did.json']]), new MockResponse('{"id":"x"}'));

        self::assertSame('{"id":"x"}', $fetcher->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json')));
        self::assertSame('https://other.example.org/did.json', $this->requests[1][1]);
    }

    public function testAPrivateNetworkIsRefused(): void
    {
        $fetcher = new SafeHttpFetcher(new SafeHttpClient($this->client(new MockResponse('{}')), new Limits(), ['issuer.example.org' => '10.0.0.1']));

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageMatches('/^The DID document at "https:\/\/issuer\.example\.org\/\.well-known\/did\.json" cannot be read: /');
        $fetcher->fetch(Url::fromString('https://issuer.example.org/.well-known/did.json'));
    }

    public function testTheDefaultClientIsBoundedAndForgetsItsAddresses(): void
    {
        $fetcher = new SafeHttpFetcher();
        $client = new ReflectionProperty(SafeHttpFetcher::class, 'client')->getValue($fetcher);
        self::assertInstanceOf(SafeHttpClient::class, $client);
        $limits = new ReflectionProperty(SafeHttpClient::class, 'limits')->getValue($client);
        self::assertInstanceOf(Limits::class, $limits);
        self::assertSame([5.0, 10.0, 262_144, 3, 'PhilOpenBadges/0.1'], [$limits->timeout, $limits->maxDuration, $limits->maxBytes, $limits->maxRedirects, $limits->userAgent]);

        $fetcher->reset();
        $this->fetcher()->reset();
        self::assertSame([], $this->requests);
    }

    public function testTheClientIsResetWhenTheFetcherIs(): void
    {
        $inner = new class(new MockHttpClient()) implements HttpClientInterface, ResetInterface {
            use DecoratorTrait;

            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
            }
        };

        new SafeHttpFetcher(new SafeHttpClient($inner))->reset();

        self::assertSame(1, $inner->resets);
    }

    private function fetcher(MockResponse ...$responses): SafeHttpFetcher
    {
        return new SafeHttpFetcher(new SafeHttpClient($this->client(...$responses), new Limits(), self::RESOLVE));
    }

    private function client(MockResponse ...$responses): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $headers = [];

            foreach (\is_array($options['headers'] ?? null) ? $options['headers'] : [] as $header) {
                if (\is_string($header)) {
                    $headers[] = $header;
                }
            }

            $this->requests[] = [$method, $url, $headers];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 404]);
        });
    }
}
