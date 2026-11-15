<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

/**
 * A feed file in place: where it is, how many items it holds, and its size in bytes.
 */
final readonly class PublishedFeed
{
    public function __construct(
        public string $path,
        public int $items,
        public int $bytes,
    ) {
    }
}
