<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

/**
 * The entries a source gives for one query, the most recent first, and whether an older page follows.
 */
final readonly class FeedPage
{
    /**
     * @param list<FeedItem> $items
     */
    public function __construct(
        public array $items,
        public bool $hasNextPage = false,
    ) {
    }
}
