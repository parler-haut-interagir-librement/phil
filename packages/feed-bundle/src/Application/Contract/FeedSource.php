<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Application\Contract;

use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\FeedPage;
use Phil\FeedBundle\Domain\ValueObject\FeedQuery;
use Phil\Publishing\Contract\PageEvent;

/**
 * A feed of the application: news, the pages of a kind of content, the events of a calendar, the episodes of a
 * podcast.
 *
 * A service that implements it is a feed, served at `/feeds/{name}.atom`, `.rss` and `.json` and announced to the
 * WebSub hubs when a page it lists is published.
 */
interface FeedSource
{
    /**
     * The name of the feed in its address: 1 to 64 lowercase letters, digits, hyphens or underscores, starting with a
     * letter or a digit, unique among the feeds.
     */
    public function name(): string;

    /**
     * The title, page, description and language of the feed, without reading its entries: every page that links to
     * the feed asks for it.
     */
    public function channel(): Channel;

    /**
     * At most `$query->limit` entries, the most recent first, from the page `$query->page`. A source may give one more
     * than the limit instead of saying that a page follows: the bundle keeps the limit and reads the extra one as an
     * older page.
     */
    public function items(FeedQuery $query): FeedPage;

    /**
     * Whether the page that went online, or offline, is one this feed lists — or listed: its hubs are then told the
     * feed changed. A feed that cannot tell answers `false`, and is announced by no page.
     */
    public function includes(PageEvent $page): bool;
}
