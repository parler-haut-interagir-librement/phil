<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\ProviderRefused;
use Phil\VirtualClassroom\Exception\ProviderUnavailable;
use Phil\VirtualClassroom\Http\BoundedHttp;
use Phil\VirtualClassroom\Http\HttpBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

#[CoversClass(BoundedHttp::class)]
final class BoundedHttpTest extends TestCase
{
    public function testTheCallIsBoundedAndFollowsNoRedirect(): void
    {
        $seen = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = [$method, $url, $options['timeout'], $options['max_duration'], $options['max_redirects'], $options['headers']];

            return new MockResponse('<response/>');
        });

        self::assertSame('<response/>', new BoundedHttp($client, new HttpBudget(2.0, 3.0, 2048))->get('https://example.com/a', 'a'));
        self::assertSame('GET', $seen[0]);
        self::assertSame('https://example.com/a', $seen[1]);
        self::assertSame(2.0, $seen[2]);
        self::assertSame(3.0, $seen[3]);
        self::assertSame(0, $seen[4]);
        self::assertIsArray($seen[5]);
        self::assertContains('Accept: application/xml, application/json', $seen[5]);
    }

    public function testAnAnswerUpToItsCeilingIsRead(): void
    {
        $client = new MockHttpClient(new MockResponse([str_repeat('a', 1000), str_repeat('b', 24)]));

        self::assertSame(1024, \strlen(new BoundedHttp($client, new HttpBudget(maxBytes: 1024))->get('https://example.com/a', 'a')));
    }

    public function testAnAnswerPastItsCeilingIsCancelled(): void
    {
        $inner = new MockHttpClient(new MockResponse([str_repeat('a', 1000), str_repeat('b', 25), str_repeat('c', 100_000)]));
        $client = new class($inner) implements HttpClientInterface {
            public ?ResponseInterface $last = null;

            public function __construct(private readonly HttpClientInterface $inner)
            {
            }

            /**
             * @param array<array-key, mixed> $options
             */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return $this->last = $this->inner->request($method, $url, $options);
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                return $this->inner->stream($responses, $timeout);
            }

            /**
             * @param array<array-key, mixed> $options
             */
            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        try {
            new BoundedHttp($client, new HttpBudget(maxBytes: 1024))->get('https://example.com/a', 'create');
            self::fail('The answer is cut.');
        } catch (InvalidResponse) {
            self::assertTrue($client->last?->getInfo('canceled'));
        }
    }

    public function testAnAnswerPastItsCeilingIsCut(): void
    {
        $client = new MockHttpClient(new MockResponse([str_repeat('a', 1000), str_repeat('b', 25), str_repeat('c', 100_000)]));

        $this->expectException(InvalidResponse::class);
        $this->expectExceptionMessageIsOrContains('The answer to "create" is larger than 1024 bytes.');

        new BoundedHttp($client, new HttpBudget(maxBytes: 1024))->get('https://example.com/a', 'create');
    }

    public function testAServerErrorIsAnUnavailableServer(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $this->expectException(ProviderUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The call "create" was answered with the server error 500.');

        new BoundedHttp($client, new HttpBudget())->get('https://example.com/a?checksum=secret', 'create');
    }

    public function testAClientErrorIsARefusal(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 499]));

        $this->expectException(ProviderRefused::class);
        $this->expectExceptionMessageIsOrContains('The call "end" was answered with the status 499.');

        new BoundedHttp($client, new HttpBudget())->get('https://example.com/a', 'end');
    }

    public function testARedirectIsARefusal(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 300]));

        $this->expectException(ProviderRefused::class);
        $this->expectExceptionMessageIsOrContains('The call "end" was answered with the status 300.');

        new BoundedHttp($client, new HttpBudget())->get('https://example.com/a', 'end');
    }

    public function testTheLastSuccessIsRead(): void
    {
        self::assertSame('ok', new BoundedHttp(new MockHttpClient(new MockResponse('ok', ['http_code' => 299])), new HttpBudget())->get('https://example.com/a', 'a'));
    }

    public function testAFailedTransportNamesTheCallAndNotTheAddress(): void
    {
        $client = new MockHttpClient(static fn (): never => throw new TransportException('Timeout for https://example.com/a?checksum=abc'));

        try {
            new BoundedHttp($client, new HttpBudget())->get('https://example.com/a?checksum=abc', 'getMeetingInfo');
            self::fail('The call succeeded.');
        } catch (ProviderUnavailable $providerUnavailable) {
            self::assertSame('The call "getMeetingInfo" could not be completed in time.', $providerUnavailable->getMessage());
        }
    }

    public function testATimeoutWhileReadingIsAnUnavailableServer(): void
    {
        $client = new MockHttpClient(new MockResponse(['a', new TransportException('Idle timeout')]));

        $this->expectException(ProviderUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The call "a" could not be completed in time.');

        new BoundedHttp($client, new HttpBudget())->get('https://example.com/a', 'a');
    }
}
