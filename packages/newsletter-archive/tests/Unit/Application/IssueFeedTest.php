<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\NewsletterArchive\Application\Contract\IssueUrls;
use Phil\NewsletterArchive\Application\FeedItem;
use Phil\NewsletterArchive\Application\IssueFeed;
use Phil\NewsletterArchive\Domain\Model\ArchivedIssue;
use Phil\NewsletterArchive\Tests\Fixture\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The latest published issues as feed entries, newest sent first, filtered by list and tag, with their addresses.
 */
#[CoversClass(IssueFeed::class)]
#[CoversClass(FeedItem::class)]
final class IssueFeedTest extends TestCase
{
    public function testTheLatestPublishedIssuesAsEntries(): void
    {
        $stage = new Stage();
        $stage->campaign('c1', 'Old news', '2026-10-01 09:00:00');
        $stage->campaign('c2', 'New news', '2026-11-01 09:00:00', 'l2');
        $stage->campaign('c3', 'Shared only', '2026-11-02 09:00:00');
        $stage->archive->publish('c1', ['news']);
        $stage->archive->publish('c2', ['events']);
        $stage->archive->share('c3');

        $feed = new IssueFeed($stage->issues, new class implements IssueUrls {
            #[Override]
            public function issue(ArchivedIssue $issue): string
            {
                return 'https://example.com/archive/'.$issue->slug;
            }

            #[Override]
            public function link(string $token): string
            {
                return 'https://example.com/archive/shared/'.$token;
            }
        });

        $items = $feed->latest('default');
        self::assertSame(['New news', 'Old news'], array_map(static fn (FeedItem $item): string => $item->title, $items));
        self::assertSame('c2', $items[0]->id);
        self::assertSame('https://example.com/archive/new-news', $items[0]->url);
        self::assertSame('This month in short', $items[0]->summary);
        self::assertStringContainsString('<strong>readers</strong>', $items[0]->html);
        self::assertEquals(new DateTimeImmutable('2026-11-01 09:00:00 UTC'), $items[0]->sentAt);
        self::assertSame(['events'], $items[0]->tags);

        self::assertCount(1, $feed->latest('default', 1));
        self::assertSame(['Old news'], array_map(static fn (FeedItem $item): string => $item->title, $feed->latest('default', 20, 'news')));
        self::assertSame(['New news'], array_map(static fn (FeedItem $item): string => $item->title, $feed->latest('default', 20, null, 'events')));
        self::assertSame([], $feed->latest('other'));
    }

    public function testWithoutALimitTheLatestTwentyComeBack(): void
    {
        $stage = new Stage();

        for ($i = 1; $i <= 21; ++$i) {
            $stage->campaign('c'.$i, 'News '.$i, \sprintf('2026-10-%02d 09:00:00', $i));
            $stage->archive->publish('c'.$i);
        }

        $items = new IssueFeed($stage->issues, new class implements IssueUrls {
            #[Override]
            public function issue(ArchivedIssue $issue): string
            {
                return $issue->slug;
            }

            #[Override]
            public function link(string $token): string
            {
                return $token;
            }
        })->latest('default');

        self::assertCount(20, $items);
        self::assertSame('News 21', $items[0]->title);
        self::assertSame('News 2', $items[19]->title);
    }
}
