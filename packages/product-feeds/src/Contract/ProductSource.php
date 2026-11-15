<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Contract;

use Phil\ProductFeeds\FeedItem;

/**
 * The products a feed publishes, one item per product or variant sold: what the catalogue of an application gives.
 *
 * `items()` should be a generator that reads its catalogue by pages, so that `FeedPublisher` writes a long catalogue
 * in constant memory. Each identifier is given once.
 */
interface ProductSource
{
    /**
     * @return iterable<FeedItem>
     */
    public function items(): iterable;
}
