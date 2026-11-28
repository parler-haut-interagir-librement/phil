<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Pairs;

use Phil\RelatedContent\Contract\PairCounts;
use Phil\RelatedContent\Exception\InvalidRule;

/**
 * Turns an order, or a view, into anonymous pair counts at the moment it happens, and keeps nothing else: no order
 * number, no customer, no session, no sequence of views.
 *
 * ```php
 * $recorder->order(['product:game', 'product:sleeves', 'product:dice']);
 * $recorder->viewedNext('product:game', 'product:expansion');
 * ```
 */
final readonly class PairRecorder
{
    public const int MAX_ITEMS_PER_ORDER = 200;

    /**
     * @param int $maxItemsPerOrder the distinct products of an order that are paired, from 2 to 200: an order of n
     *                              products makes n × (n − 1) / 2 pairs
     *
     * @throws InvalidRule when the bound lies outside 2 to 200
     */
    public function __construct(
        private PairCounts $counts,
        private int $maxItemsPerOrder = 50,
    ) {
        if ($maxItemsPerOrder < 2 || $maxItemsPerOrder > self::MAX_ITEMS_PER_ORDER) {
            throw InvalidRule::outOfRange('maxItemsPerOrder', $maxItemsPerOrder, 2, self::MAX_ITEMS_PER_ORDER);
        }
    }

    /**
     * Counts each pair of distinct products of an order once, whatever their quantities; past the bound, the first
     * products of the order are paired.
     *
     * @param list<string> $productIds
     *
     * @return int the pairs counted
     */
    public function order(array $productIds): int
    {
        $products = \array_slice(array_unique($productIds), 0, $this->maxItemsPerOrder);
        $pairs = 0;

        foreach ($products as $i => $from) {
            foreach (\array_slice($products, $i + 1) as $to) {
                $this->counts->add(Signal::BoughtTogether, $from, $to);
                ++$pairs;
            }
        }

        return $pairs;
    }

    /**
     * Counts a product viewed right after another; the same product twice counts nothing.
     */
    public function viewedNext(string $from, string $to): void
    {
        $this->counts->add(Signal::ViewedNext, $from, $to);
    }
}
