<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SocialCardsBundle\Application\FetchedPage;
use Phil\SocialCardsBundle\Infrastructure\Http\GuardedPageFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(GuardedPageFetcher::class)]
final class GuardedPageFetcherTest extends TestCase
{
    /** Pinned addresses, so that no test resolves a name. */
    private const array RESOLVE = ['example.com' => '93.184.215.14', 'example.org' => '93.184.215.15', 'internal.example' => '10.0.0.5'];

    /**
     * @param list<MockResponse>|callable(string, string, array<array-key, mixed>): MockResponse $responses
     */
    private function fetcher(array|callable $responses, Limits $limits = new Limits()): GuardedPageFetcher
    {
        return new GuardedPageFetcher(new SafeHttpClient(new MockHttpClient($responses), $limits, self::RESOLVE));
    }

    public function testTheHeadOfAPageIsRead(): void
    {
        /** @var ArrayObject<int, array<array-key, mixed>> $sent */
        $sent = new ArrayObject();
        $fetcher = $this->fetcher(static function (string $method, string $url, array $options) use ($sent): MockResponse {
            $sent[] = $options;

            return new MockResponse(['<html><head><title>T</title>', '<meta property="og:title" content="X"></head>', '<body>'.str_repeat('x', 1000).'</body>'], ['response_headers' => ['Content-Type' => 'text/html; charset="ISO-8859-1"']]);
        });

        $page = $fetcher->fetch('https://example.com/post');

        self::assertInstanceOf(FetchedPage::class, $page);
        self::assertSame(['<html><head><title>T</title><meta property="og:title" content="X"></head>', 'https://example.com/post', 'ISO-8859-1'], [$page->html, $page->url, $page->charset]);
        $headers = $sent[0]['headers'] ?? null;
        self::assertIsArray($headers);
        self::assertContains('Accept: text/html, application/xhtml+xml;q=0.9', $headers);
    }

    public function testThePageIsReadUpToTheBytesOfTheLimits(): void
    {
        $page = $this->fetcher([new MockResponse([str_repeat('a', 3000), str_repeat('b', 3000)], ['response_headers' => ['Content-Type' => 'application/xhtml+xml']])], new Limits(maxBytes: 4000))->fetch('https://example.com/');

        self::assertSame([str_repeat('a', 3000).str_repeat('b', 1000), null], [$page?->html, $page?->charset]);
    }

    public function testTheAddressAfterTheRedirects(): void
    {
        $fetcher = $this->fetcher([
            new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://example.org/final']]),
            new MockResponse('<head><title>Final</title></head>', ['response_headers' => ['Content-Type' => 'text/html']]),
        ]);

        self::assertSame('https://example.org/final', $fetcher->fetch('https://example.com/start')?->url);
    }

    /**
     * @return iterable<string, array{MockResponse}>
     */
    public static function refusedAnswers(): iterable
    {
        yield 'not found' => [new MockResponse('<head></head>', ['http_code' => 404, 'response_headers' => ['Content-Type' => 'text/html']])];
        yield 'no content' => [new MockResponse('', ['http_code' => 204, 'response_headers' => ['Content-Type' => 'text/html']])];
        yield 'an image' => [new MockResponse('PNG', ['response_headers' => ['Content-Type' => 'image/png']])];
        yield 'no type' => [new MockResponse('<head></head>')];
        yield 'a type that only starts like HTML' => [new MockResponse('<head></head>', ['response_headers' => ['Content-Type' => 'text/htmlx']])];
        yield 'a failed transfer' => [new MockResponse('', ['error' => 'Connection refused'])];
        yield 'a redirect to a private host' => [new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://internal.example/']])];
    }

    #[DataProvider('refusedAnswers')]
    public function testAnAnswerThatIsNotAPageGivesNothing(MockResponse $response): void
    {
        self::assertNull($this->fetcher([$response, new MockResponse('<head></head>', ['response_headers' => ['Content-Type' => 'text/html']])])->fetch('https://example.com/'));
    }

    public function testAnAddressThatIsNotPublicGivesNothing(): void
    {
        $fetcher = $this->fetcher(static fn (): MockResponse => self::fail('A private address is never fetched.'));

        self::assertNull($fetcher->fetch('http://169.254.169.254/latest/meta-data/'));
        self::assertNull($fetcher->fetch('https://internal.example/'));
    }

    public function testTheClientIsResetBetweenRequests(): void
    {
        $inner = new class(new MockHttpClient()) implements HttpClientInterface, ResetInterface {
            use DecoratorTrait;

            public int $resets = 0;

            public function reset(): void
            {
                ++$this->resets;
            }
        };
        new GuardedPageFetcher(new SafeHttpClient($inner))->reset();

        self::assertSame(1, $inner->resets);
        self::assertInstanceOf(GuardedPageFetcher::class, new GuardedPageFetcher());
    }
}
