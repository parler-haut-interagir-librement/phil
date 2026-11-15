<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Contract;

use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedItem;

/**
 * Writes a feed as text, in three parts, so that a feed of any length is written one item at a time: what comes before
 * the items, each item, and what comes after them. `FeedFormat` gives the package's two; an application writes its own
 * for another format.
 */
interface FeedSerializer
{
    /**
     * What comes before the first item: a declaration, a header row, the channel's title, link and description.
     */
    public function header(FeedChannel $channel): string;

    /**
     * One item, ending where the next one starts.
     */
    public function item(FeedItem $item): string;

    /**
     * What comes after the last item.
     */
    public function footer(): string;
}
