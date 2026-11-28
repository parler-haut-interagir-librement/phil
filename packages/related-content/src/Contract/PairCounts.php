<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Contract;

use Phil\RelatedContent\Pairs\PairCount;
use Phil\RelatedContent\Pairs\Signal;

/**
 * Anonymous counts by pair of products: how many orders held both, how many times one was viewed right after the other.
 * Never who: an implementation stores the two identifiers and a count, nothing about a person, an order or a session.
 */
interface PairCounts
{
    /**
     * Adds to the count of a pair; a pair bought together is the same pair both ways.
     */
    public function add(Signal $signal, string $from, string $to, int $count = 1): void;

    /**
     * The pairs of this item with the highest counts, highest first.
     *
     * @return list<PairCount>
     */
    public function top(Signal $signal, string $id, int $limit): array;

    /**
     * Removes every pair of a retired item.
     */
    public function forget(string $id): void;
}
