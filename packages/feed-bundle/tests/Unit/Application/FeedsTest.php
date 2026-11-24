<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Generator;
use Phil\FeedBundle\Application\Exception\InvalidSource;
use Phil\FeedBundle\Application\Exception\UnknownFeed;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Tests\Fixtures\StaticSource;
use Phil\Publishing\PagePublished;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Feeds::class)]
#[CoversClass(UnknownFeed::class)]
#[CoversClass(InvalidSource::class)]
final class FeedsTest extends TestCase
{
    public function testTheSourcesAreFoundByName(): void
    {
        $news = new StaticSource('news');
        $events = new StaticSource('events_2026', prefix: 'event:');
        $feeds = new Feeds([$news, $events]);

        self::assertSame(['news', 'events_2026'], $feeds->names());
        self::assertSame($events, $feeds->source('events_2026'));
        self::assertSame($news, $feeds->source('news'));
    }

    public function testAnUnknownNameIsRefused(): void
    {
        $this->expectException(UnknownFeed::class);
        $this->expectExceptionMessageIsOrContains('No feed is named "'.str_repeat('x', 64).'".');

        new Feeds([new StaticSource('news')])->source(str_repeat('x', 70));
    }

    public function testAnUnknownNameIsCutAtSixtyFourCharacters(): void
    {
        $this->expectException(UnknownFeed::class);
        $this->expectExceptionMessageIsOrContains('No feed is named "a'.str_repeat('é', 63).'".');

        new Feeds([new StaticSource('news')])->source('a'.str_repeat('é', 69));
    }

    public function testAWrongSourceNameIsCutAtSixtyFourCharacters(): void
    {
        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessageIsOrContains('The feed source '.StaticSource::class.' is named "'.str_repeat('é', 64).'": a name');

        new Feeds([new StaticSource(str_repeat('é', 70))])->names();
    }

    public function testASourceNamedAsNoAddressCanHoldIsRefused(): void
    {
        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessageIsOrContains('The feed source '.StaticSource::class.' is named "News": a name is 1 to 64 lowercase letters');

        new Feeds([new StaticSource('News')])->names();
    }

    public function testTheNamesAreBounded(): void
    {
        self::assertSame([str_repeat('a', 64), '0-_'], new Feeds([new StaticSource(str_repeat('a', 64)), new StaticSource('0-_')])->names());

        foreach ([str_repeat('a', 65), '-a', '_a', "a\n", ''] as $name) {
            try {
                new Feeds([new StaticSource($name)])->names();
                self::fail($name.' is refused');
            } catch (InvalidSource) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTwoSourcesOfOneNameAreRefused(): void
    {
        $this->expectException(InvalidSource::class);
        $this->expectExceptionMessageIsOrContains('The feed sources '.StaticSource::class.' and '.StaticSource::class.' are both named "news".');

        new Feeds([new StaticSource('news'), new StaticSource('news')])->source('news');
    }

    public function testTheSourcesAreIndexedOnce(): void
    {
        $calls = 0;
        $sources = static function () use (&$calls): Generator {
            ++$calls;

            yield new StaticSource('news');
        };
        $feeds = new Feeds($sources());

        $feeds->names();
        $feeds->source('news');
        self::assertSame(1, $calls);
    }

    public function testAFeedHoldsOnePageOfItsSource(): void
    {
        $source = new StaticSource('news', $this->items(5));
        $feeds = new Feeds([$source], 2);

        $first = $feeds->feed('news');
        self::assertSame(['urn:n:0', 'urn:n:1'], array_map(static fn (FeedItem $item): string => $item->id, $first->items));
        self::assertSame([1, false, 'News'], [$first->page, $first->hasNextPage, $first->channel->title]);

        $third = $feeds->feed('news', 3);
        self::assertSame(['urn:n:4'], array_map(static fn (FeedItem $item): string => $item->id, $third->items));
        self::assertSame([2, 3], [$source->queries[1]->limit, $source->queries[1]->page]);
        self::assertSame(20, new Feeds([$source])->feed('news')->page * 20);
        self::assertSame(20, $source->queries[2]->limit);
    }

    public function testAnExtraEntryMeansAnOlderPage(): void
    {
        $feeds = new Feeds([new StaticSource('news', $this->items(5), extra: 1)], 2);

        $first = $feeds->feed('news');
        self::assertCount(2, $first->items);
        self::assertTrue($first->hasNextPage);
        self::assertFalse($feeds->feed('news', 3)->hasNextPage);
        self::assertTrue(new Feeds([new StaticSource('news', $this->items(1), saysNext: true)], 2)->feed('news')->hasNextPage);
    }

    public function testTheFeedsOfAPage(): void
    {
        $feeds = new Feeds([new StaticSource('news'), new StaticSource('events', prefix: 'event:'), new StaticSource('all', prefix: '')]);
        $page = new PagePublished(Url::fromString('https://example.org/e/1'), 'fr', true, 'event:1');

        self::assertSame(['events', 'all'], $feeds->including($page));
    }

    /**
     * @return list<FeedItem>
     */
    private function items(int $count): array
    {
        $items = [];

        for ($i = 0; $i < $count; ++$i) {
            $items[] = new FeedItem('urn:n:'.$i, 'Item '.$i, Url::fromString('https://example.org/'.$i), new DateTimeImmutable('2026-11-21T08:00:00Z'));
        }

        return $items;
    }
}
