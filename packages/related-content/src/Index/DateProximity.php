<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Index;

use DateTimeImmutable;
use Phil\RelatedContent\Contract\Index;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Item;

/**
 * Close dates: the full weight on the same instant, falling in a straight line to zero at `windowDays` apart. Nothing
 * when either item has no date.
 *
 * ```php
 * new DateProximity(50, windowDays: 365);
 * ```
 */
final readonly class DateProximity implements Index
{
    public const int MAX_WINDOW_DAYS = 36_500;

    /**
     * @throws InvalidRule when the weight is negative or not finite, or the window outside 1 to 36,500 days
     */
    public function __construct(
        public float $weight,
        public int $windowDays = 365,
    ) {
        if (!is_finite($weight) || $weight < 0.0) {
            throw InvalidRule::outOfRange('date weight', $weight, 0, \PHP_FLOAT_MAX);
        }

        if ($windowDays < 1 || $windowDays > self::MAX_WINDOW_DAYS) {
            throw InvalidRule::outOfRange('windowDays', $windowDays, 1, self::MAX_WINDOW_DAYS);
        }
    }

    public function score(Item $item, Item $candidate): float
    {
        if (!$item->date instanceof DateTimeImmutable || !$candidate->date instanceof DateTimeImmutable) {
            return 0.0;
        }

        $days = abs($item->date->getTimestamp() - $candidate->date->getTimestamp()) / 86_400;

        return $this->weight * max(0.0, 1.0 - $days / $this->windowDays);
    }
}
