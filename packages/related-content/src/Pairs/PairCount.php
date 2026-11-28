<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Pairs;

/**
 * The count of a pair, seen from one of its items: the other item, and how many times.
 */
final readonly class PairCount
{
    public function __construct(
        public string $other,
        public int $count,
    ) {
    }
}
