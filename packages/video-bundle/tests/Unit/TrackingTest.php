<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Tests\Unit;

use ArrayObject;
use Phil\TimedText\Cue;
use Phil\TimedText\Track;
use Phil\VideoBundle\Application\Contract\ViewingConsent;
use Phil\VideoBundle\Application\Event\VideoProgressed;
use Phil\VideoBundle\Application\Heatmap;
use Phil\VideoBundle\Application\NoViewingConsent;
use Phil\VideoBundle\Application\ViewingProgress;
use Phil\VideoBundle\Application\ViewingTracker;
use Phil\VideoBundle\Infrastructure\Cache\CacheViewingStore;
use Phil\VideoBundle\Infrastructure\Http\ProgressController;
use Phil\VideoBundle\Infrastructure\Http\TextTrackResponse;
use Phil\VideoBundle\Infrastructure\Memory\Counts;
use Phil\VideoBundle\Infrastructure\Memory\InMemoryViewingStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ViewingTracker::class)]
#[CoversClass(NoViewingConsent::class)]
#[CoversClass(VideoProgressed::class)]
#[CoversClass(InMemoryViewingStore::class)]
#[CoversClass(Counts::class)]
#[CoversClass(CacheViewingStore::class)]
#[CoversClass(ProgressController::class)]
#[CoversClass(TextTrackResponse::class)]
final class TrackingTest extends TestCase
{
    private function consent(bool $granted): ViewingConsent
    {
        return new readonly class($granted) implements ViewingConsent {
            public function __construct(private bool $granted)
            {
            }

            public function granted(): bool
            {
                return $this->granted;
            }
        };
    }

    /**
     * @return array{ViewingTracker, InMemoryViewingStore, ArrayObject<int, VideoProgressed>}
     */
    private function tracker(bool $granted, bool $enabled = true): array
    {
        $store = new InMemoryViewingStore();
        $dispatcher = new EventDispatcher();
        $events = new ArrayObject();
        $dispatcher->addListener(VideoProgressed::class, static function (VideoProgressed $event) use ($events): void {
            $events[] = $event;
        });

        return [new ViewingTracker($this->consent($granted), $store, $dispatcher, $enabled), $store, $events];
    }

    public function testAViewingIsCountedAndSaidWithConsent(): void
    {
        [$tracker, $store, $events] = $this->tracker(true);
        $progress = ViewingProgress::fromString('talk', '1'.str_repeat('0', 99));

        self::assertTrue($tracker->isEnabled());
        self::assertTrue($tracker->record($progress));
        self::assertTrue($tracker->record(ViewingProgress::fromString('talk', '11'.str_repeat('0', 98))));
        self::assertSame(2, $store->heatmap('talk')->viewings);
        self::assertSame([2, 1, 0], \array_slice($store->heatmap('talk')->counts, 0, 3));
        self::assertCount(100, $store->heatmap('talk')->counts);
        self::assertCount(2, $events);
        self::assertSame($progress, $events[0]?->progress);
    }

    public function testNothingIsCountedWithoutConsentOrWithTrackingOff(): void
    {
        foreach ([$this->tracker(false), $this->tracker(true, false)] as [$tracker, $store, $events]) {
            self::assertFalse($tracker->isEnabled());
            self::assertFalse($tracker->record(ViewingProgress::fromString('talk', str_repeat('1', 100))));
            self::assertEquals(new Heatmap(), $store->heatmap('talk'));
            self::assertCount(0, $events);
        }

        self::assertFalse(new NoViewingConsent()->granted());
    }

    public function testTheMemoryStoreForgetsBetweenRequests(): void
    {
        $store = new InMemoryViewingStore();
        $store->add(ViewingProgress::fromString('a', str_repeat('1', 100)));
        $store->reset();

        self::assertSame(0, $store->heatmap('a')->viewings);
    }

    public function testTheCacheStoreKeepsOneItemPerVideo(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheViewingStore($pool);
        $store->add(ViewingProgress::fromString('a', '1'.str_repeat('0', 99)));
        $store->add(ViewingProgress::fromString('a', '11'.str_repeat('0', 98)));
        $store->add(ViewingProgress::fromString('b', str_repeat('1', 100)));

        self::assertSame(2, $store->heatmap('a')->viewings);
        self::assertSame([2, 1, 0], \array_slice($store->heatmap('a')->counts, 0, 3));
        self::assertSame(1, $store->heatmap('b')->viewings);
        self::assertTrue($pool->hasItem('phil_video.heatmap.a'));
        self::assertEquals(new Heatmap(), $store->heatmap('c'));
    }

    public function testACorruptItemIsAnEmptyHeatMap(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheViewingStore($pool);

        foreach (['text', ['viewings' => '2', 'counts' => []], ['viewings' => 2], ['viewings' => 2, 'counts' => 'x']] as $value) {
            $pool->save($pool->getItem('phil_video.heatmap.a')->set($value));
            self::assertEquals(new Heatmap(), $store->heatmap('a'));
        }

        $pool->save($pool->getItem('phil_video.heatmap.a')->set(['viewings' => 2, 'counts' => [1, 'x', 2]]));
        self::assertSame([1, 0, 2, 0], \array_slice($store->heatmap('a')->counts, 0, 4));
        self::assertCount(100, $store->heatmap('a')->counts);
    }

    /**
     * @param array<string, string> $headers
     */
    private function post(string $body, array $headers = ['Sec-Fetch-Site' => 'same-origin']): Request
    {
        $request = Request::create('https://example.com/_phil/video/progress', 'POST', content: $body);

        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    public function testTheControllerCountsAViewingFromThePagesOfTheSite(): void
    {
        [$tracker, $store] = $this->tracker(true);
        $response = new ProgressController($tracker)($this->post('{"video":"talk","parts":"'.str_repeat('1', 100).'"}'));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(1, $store->heatmap('talk')->viewings);

        $origin = new ProgressController($tracker)($this->post('{"video":"talk","parts":"'.str_repeat('1', 100).'"}', ['Origin' => 'https://example.com']));
        self::assertSame(204, $origin->getStatusCode());
        self::assertSame(2, $store->heatmap('talk')->viewings);
    }

    public function testTheAnswerSaysNothingOfConsent(): void
    {
        [$tracker, $store] = $this->tracker(false);

        self::assertSame(204, new ProgressController($tracker)($this->post('{"video":"talk","parts":"'.str_repeat('1', 100).'"}'))->getStatusCode());
        self::assertSame(0, $store->heatmap('talk')->viewings);
    }

    /**
     * @return iterable<string, array{string, array<string, string>, int}>
     */
    public static function refused(): iterable
    {
        $body = '{"video":"talk","parts":"'.str_repeat('1', 100).'"}';

        yield 'another site' => [$body, ['Sec-Fetch-Site' => 'cross-site'], 403];
        yield 'same site, other origin' => [$body, ['Sec-Fetch-Site' => 'same-site'], 403];
        yield 'another origin' => [$body, ['Origin' => 'https://evil.example.com'], 403];
        yield 'no origin' => [$body, [], 403];
        yield 'too large' => [str_pad($body, ProgressController::MAX_BYTES + 1), ['Sec-Fetch-Site' => 'same-origin'], 413];
        yield 'not JSON' => ['{', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'nested' => ['{"video":{"a":1},"parts":"1"}', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'a list' => ['["talk"]', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'no parts' => ['{"video":"talk"}', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'parts a number' => ['{"video":"talk","parts":1}', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'no video' => ['{"parts":"1"}', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'bad parts' => ['{"video":"talk","parts":"12"}', ['Sec-Fetch-Site' => 'same-origin'], 400];
        yield 'a string' => ['"x"', ['Sec-Fetch-Site' => 'same-origin'], 400];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('refused')]
    public function testTheControllerRefusesWhatNoPlayerSends(string $body, array $headers, int $status): void
    {
        [$tracker, $store] = $this->tracker(true);
        $response = new ProgressController($tracker)($this->post($body, $headers));

        self::assertSame($status, $response->getStatusCode());
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertSame(0, $store->heatmap('talk')->viewings);
    }

    public function testABodyOfAKilobyteIsRead(): void
    {
        [$tracker] = $this->tracker(true);
        $body = str_pad('{"video":"talk","parts":"'.str_repeat('1', 100).'"}', ProgressController::MAX_BYTES);

        self::assertSame(204, new ProgressController($tracker)($this->post($body))->getStatusCode());
    }

    public function testATextTrackIsServedAsWebVtt(): void
    {
        $response = TextTrackResponse::webVtt(new Track([new Cue(0, 1_000, 'Hello')]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame("WEBVTT\n\n00:00:00.000 --> 00:00:01.000\nHello\n", $response->getContent());
        self::assertSame('text/vtt; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function testTrackingIsOnByDefault(): void
    {
        self::assertTrue(new ViewingTracker($this->consent(true), new InMemoryViewingStore(), new EventDispatcher())->isEnabled());
    }
}
