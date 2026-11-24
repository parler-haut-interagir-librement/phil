<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit\Probe;

use Phil\LinkChecker\Probe\HopRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(HopRecorder::class)]
final class HopRecorderTest extends TestCase
{
    public function testTheCodesOfTheAnswersAreKeptInOrder(): void
    {
        $recorder = new HopRecorder(new MockHttpClient([
            new MockResponse('', ['http_code' => 301]),
            new MockResponse('ok', ['http_code' => 200]),
        ]));

        $first = $recorder->request('GET', 'https://example.com/a');
        $second = $recorder->request('GET', 'https://example.com/b');
        $first->getStatusCode();
        $second->getStatusCode();

        self::assertSame([301, 200], $recorder->codes());

        self::assertGreaterThan(0, iterator_count($recorder->stream($second)));

        $recorder->forget();
        self::assertSame([], $recorder->codes());
    }

    public function testAtMostThirtyTwoAnswersAreKept(): void
    {
        $recorder = new HopRecorder(new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 204])));

        for ($i = 0; $i < 33; ++$i) {
            $recorder->request('HEAD', 'https://example.com/')->getStatusCode();
        }

        self::assertCount(32, $recorder->codes());
    }

    public function testAnAnswerWithoutCodeCountsAsZero(): void
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getInfo')->willReturn(null);
        $client = self::createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $recorder = new HopRecorder($client);
        $recorder->request('GET', 'https://example.com/');

        self::assertSame([0], $recorder->codes());
    }

    public function testOptionsMakeANewRecorderWithoutAnswers(): void
    {
        $recorder = new HopRecorder(new MockHttpClient([new MockResponse('', ['http_code' => 200]), new MockResponse('', ['http_code' => 404])]));
        $recorder->request('GET', 'https://example.com/')->getStatusCode();

        $other = $recorder->withOptions(['headers' => ['X-Test' => '1']]);

        self::assertNotSame($recorder, $other);
        self::assertSame([], $other->codes());
        self::assertSame([200], $recorder->codes());

        $other->request('GET', 'https://example.com/')->getStatusCode();
        self::assertSame([404], $other->codes());
    }

    public function testResetForgetsTheAnswersAndResetsTheTransport(): void
    {
        $inner = new class implements HttpClientInterface, ResetInterface {
            public int $resets = 0;

            private readonly MockHttpClient $client;

            public function __construct()
            {
                $this->client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 200]));
            }

            /**
             * @param array<mixed> $options
             */
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                return $this->client->request($method, $url, $options);
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                return $this->client->stream($responses, $timeout);
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

        $recorder = new HopRecorder($inner);
        $recorder->request('GET', 'https://example.com/')->getStatusCode();
        $recorder->forget();
        self::assertSame(0, $inner->resets);

        $recorder->request('GET', 'https://example.com/')->getStatusCode();
        $recorder->reset();
        self::assertSame([], $recorder->codes());
        self::assertSame(1, $inner->resets);

        $plain = new HopRecorder(self::createStub(HttpClientInterface::class));
        $plain->reset();
        self::assertSame([], $plain->codes());
    }
}
