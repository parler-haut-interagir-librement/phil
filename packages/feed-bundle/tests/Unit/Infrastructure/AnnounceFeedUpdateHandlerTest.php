<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Application\Writer\AtomWriter;
use Phil\FeedBundle\Application\Writer\JsonFeedWriter;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdateHandler;
use Phil\FeedBundle\Infrastructure\WebSub\Exception\HubNotificationFailed;
use Phil\FeedBundle\Tests\Fixtures\FixedFeedUrls;
use Phil\FeedBundle\Tests\Fixtures\RecordingLogger;
use Phil\FeedBundle\Tests\Fixtures\StaticSource;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(AnnounceFeedUpdateHandler::class)]
#[CoversClass(AnnounceFeedUpdate::class)]
#[CoversClass(HubNotificationFailed::class)]
final class AnnounceFeedUpdateHandlerTest extends TestCase
{
    /** Hosts pinned to addresses, so that no test resolves a name: two public ones of the documentation, one private. */
    private const array RESOLVE = [
        'hub.example.net' => '93.184.215.14',
        'other.example.net' => '93.184.215.15',
        'internal.example.net' => '10.0.0.7',
    ];

    /** @var list<array{string, string, array<array-key, mixed>}> */
    private array $sent = [];

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
    }

    public function testEachHubIsToldOfEachFormat(): void
    {
        $this->handler([new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 202]), new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 200])], ['https://hub.example.net/', 'https://other.example.net/publish'])(new AnnounceFeedUpdate('news'));

        self::assertSame([
            ['POST', 'https://hub.example.net/', 'hub.mode=publish&hub.url=https%3A%2F%2Fexample.org%2Ffeeds%2Fnews.atom'],
            ['POST', 'https://hub.example.net/', 'hub.mode=publish&hub.url=https%3A%2F%2Fexample.org%2Ffeeds%2Fnews.json'],
            ['POST', 'https://other.example.net/publish', 'hub.mode=publish&hub.url=https%3A%2F%2Fexample.org%2Ffeeds%2Fnews.atom'],
            ['POST', 'https://other.example.net/publish', 'hub.mode=publish&hub.url=https%3A%2F%2Fexample.org%2Ffeeds%2Fnews.json'],
        ], array_map(static fn (array $request): array => [$request[0], $request[1], \is_string($request[2]['body'] ?? null) ? $request[2]['body'] : ''], $this->sent));
        $headers = $this->sent[0][2]['headers'] ?? [];
        self::assertIsArray($headers);
        self::assertContains('Content-Type: application/x-www-form-urlencoded', $headers);
        self::assertSame([], $this->logger->records);
    }

    public function testAPrivateHubIsRefusedForGood(): void
    {
        $this->handler([new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 204])], ['https://internal.example.net/', 'https://hub.example.net/'])(new AnnounceFeedUpdate('news'));

        self::assertSame(['https://hub.example.net/', 'https://hub.example.net/'], array_column($this->sent, 1));
        self::assertCount(2, $this->logger->records);
        self::assertSame([LogLevel::WARNING, AnnounceFeedUpdateHandler::REFUSED, 'https://internal.example.net/'], [$this->logger->records[0][0], $this->logger->records[0][1], $this->logger->records[0][2]['hub'] ?? null]);
        $reason = $this->logger->records[0][2]['reason'] ?? null;
        self::assertIsString($reason);
        self::assertStringContainsString('private, loopback, link-local or reserved network', $reason);
    }

    public function testAHubThatRefusesIsRetriedOnceEveryHubWasTried(): void
    {
        $handler = $this->handler([new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 503]), new MockResponse('', ['http_code' => 204]), new MockResponse('', ['http_code' => 204])], ['https://hub.example.net/', 'https://other.example.net/']);

        try {
            $handler(new AnnounceFeedUpdate('news'));
            self::fail('The failure is handed back to Messenger.');
        } catch (HubNotificationFailed $hubNotificationFailed) {
            self::assertSame('The hubs https://hub.example.net/ did not take the update of the feed "news".', $hubNotificationFailed->getMessage());
        }

        self::assertCount(4, $this->sent);
        self::assertSame([LogLevel::WARNING, AnnounceFeedUpdateHandler::FAILED, ['hub' => 'https://hub.example.net/', 'topic' => 'https://example.org/feeds/news.atom', 'reason' => 'status 503']], $this->logger->records[0]);
        self::assertCount(2, $this->logger->records);
    }

    public function testAHubThatDoesNotAnswerIsRetried(): void
    {
        $handler = $this->handler([new MockResponse('', ['error' => 'timeout']), new MockResponse('', ['http_code' => 204]), new MockResponse('', ['error' => 'timeout']), new MockResponse('', ['error' => 'timeout'])], ['https://hub.example.net/', 'https://other.example.net/']);

        try {
            $handler(new AnnounceFeedUpdate('news'));
            self::fail('The failure is handed back to Messenger.');
        } catch (HubNotificationFailed $hubNotificationFailed) {
            self::assertSame('The hubs https://hub.example.net/, https://other.example.net/ did not take the update of the feed "news".', $hubNotificationFailed->getMessage());
        }

        self::assertSame(AnnounceFeedUpdateHandler::FAILED, $this->logger->records[0][1]);
        self::assertSame('https://hub.example.net/', $this->logger->records[0][2]['hub'] ?? null);
        self::assertSame('https://example.org/feeds/news.atom', $this->logger->records[0][2]['topic'] ?? null);
        self::assertNotSame('', $this->logger->records[0][2]['reason'] ?? '');
    }

    public function testAFeedThatNoLongerExistsIsSkipped(): void
    {
        $this->handler([], ['https://hub.example.net/'])(new AnnounceFeedUpdate('gone'));

        self::assertSame([], $this->sent);
        self::assertSame([[LogLevel::INFO, AnnounceFeedUpdateHandler::UNKNOWN, ['feed' => 'gone']]], $this->logger->records);
    }

    public function testItWorksWithoutALogger(): void
    {
        $client = new SafeHttpClient(new MockHttpClient([new MockResponse('', ['http_code' => 500]), new MockResponse('', ['error' => 'timeout'])]), resolve: self::RESOLVE);
        $handler = new AnnounceFeedUpdateHandler(new Feeds([new StaticSource('news')]), new FeedWriters([new AtomWriter()]), new FixedFeedUrls(), $client, [Url::fromString('https://internal.example.net/'), Url::fromString('https://hub.example.net/'), Url::fromString('https://other.example.net/')]);
        $handler(new AnnounceFeedUpdate('gone'));

        $this->expectException(HubNotificationFailed::class);
        $this->expectExceptionMessageIsOrContains('The hubs https://hub.example.net/, https://other.example.net/ did not take');
        $handler(new AnnounceFeedUpdate('news'));
    }

    /**
     * @param list<MockResponse> $responses
     * @param list<string>       $hubs
     */
    private function handler(array $responses, array $hubs): AnnounceFeedUpdateHandler
    {
        $client = new SafeHttpClient(new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            $this->sent[] = [$method, $url, $options];
            $response = array_shift($responses);

            if (null === $response) {
                throw new TransportException('No more answers.');
            }

            return $response;
        }), resolve: self::RESOLVE);

        return new AnnounceFeedUpdateHandler(new Feeds([new StaticSource('news')]), new FeedWriters([new AtomWriter(), new JsonFeedWriter()]), new FixedFeedUrls(), $client, array_map(static fn (string $hub): Url => Url::fromString($hub), $hubs), $this->logger);
    }
}
