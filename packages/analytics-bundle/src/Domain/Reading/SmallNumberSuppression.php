<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Reading;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The small-number rule, applied **when counters are read**, never when they are stored: the stored counter stays
 * whole, and only what a dashboard shows is grouped.
 *
 * - A counter at or above the threshold is shown on its own.
 * - A counter under the threshold joins the "other" group, together with the counter already stored under
 *   {@see CounterKey::OTHER}.
 * - **The group is shown only if its sum meets the threshold.** A group of a single small counter would show the
 *   very value it is meant to hide; a group of several whose sum is under the threshold gives them away too — two
 *   counters summing to 2 are each 1, since a counter is at least 1. Such counters are not shown at all.
 *
 * A counter of zero is no counter and is left out.
 */
final readonly class SmallNumberSuppression
{
    public function __construct(
        private SmallNumberThreshold $threshold,
    ) {
    }

    /**
     * @param array<string, int> $counts counters by dimension value
     */
    public function apply(array $counts): SuppressedCounts
    {
        $shown = [];
        $grouped = [];

        foreach ($counts as $value => $count) {
            if ($count < 0) {
                throw InvalidValue::because(\sprintf('The counter "%s" is negative (%d).', $value, $count));
            }

            if (0 === $count) {
                continue;
            }

            if (CounterKey::OTHER !== $value && $this->threshold->isMetBy($count)) {
                $shown[$value] = $count;

                continue;
            }

            $grouped[] = $count;
        }

        arsort($shown);
        $sum = array_sum($grouped);

        if ($this->threshold->isMetBy($sum)) {
            return new SuppressedCounts($shown, $sum, 0);
        }

        return new SuppressedCounts($shown, null, \count($grouped));
    }
}
