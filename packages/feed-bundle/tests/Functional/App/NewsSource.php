<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Functional\App;

use DateTimeImmutable;
use Override;
use Phil\FeedBundle\Application\Contract\FeedSource;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;
use Phil\Url\Url;

/**
 * Three news items of an application, its own source, autoconfigured: two pages at the limit of the test kernel.
 */
final class NewsSource implements FeedSource
{
    #[Override]
    public function name(): string
    {
        return 'news';
    }

    #[Override]
    public function channel(): Channel
    {
        return new Channel('News "&" more', Url::fromString('https://example.com/news'), 'What happens.', 'en');
    }

    #[Override]
    public function items(FeedQuery $query): FeedPage
    {
        $items = [];

        foreach (['2026-11-21T08:00:00Z', '2026-11-20T08:00:00Z', '2026-11-19T08:00:00Z'] as $index => $date) {
            $items[] = new FeedItem('tag:example.com,2026:news-'.$index, 'Item '.$index, Url::fromString('https://example.com/news/'.$index), new DateTimeImmutable($date), summary: 'Summary <'.$index.'>');
        }

        return new FeedPage(\array_slice($items, $query->offset(), $query->limit + 1));
    }

    #[Override]
    public function includes(PageEvent $page): bool
    {
        return str_starts_with($page->ownerId, 'news:');
    }
}
