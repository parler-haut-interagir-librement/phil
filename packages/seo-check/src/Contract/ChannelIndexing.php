<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Contract;

use Phil\Publishing\PagePublished;

/**
 * Whether a page is indexable in each storefront or channel the application serves it in: a product not sold in one
 * storefront, a channel kept away from search engines.
 */
interface ChannelIndexing
{
    /**
     * Whether search engines may index the page in this channel; null when the channel follows what the page was
     * published as.
     */
    public function isIndexable(PagePublished $event, string $channel): ?bool;
}
