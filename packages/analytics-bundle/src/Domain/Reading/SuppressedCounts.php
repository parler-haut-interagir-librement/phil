<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Reading;

/**
 * Counters ready to be shown: those at or above the threshold, and the "other" group when it may be shown.
 */
final readonly class SuppressedCounts
{
    /**
     * @param array<string, int> $shown  the counters shown on their own, by dimension value, largest first
     * @param int|null           $others the "other" group, or `null` when it is not shown
     * @param int                $hidden how many counters are shown neither on their own nor in a group
     */
    public function __construct(
        public array $shown,
        public ?int $others,
        public int $hidden,
    ) {
    }
}
