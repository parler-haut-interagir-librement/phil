<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * A period of a habit's schedule: its first and last days, the check-ins it asks, and the number of days it stands for
 * in the strength — its length, or seven divided by the number of due days for a habit due on some days of the week.
 */
final readonly class Period
{
    public function __construct(
        public Day $first,
        public Day $last,
        public int $required,
        public float $span,
    ) {
    }

    public function holds(Day $day): bool
    {
        return !$day->isBefore($this->first) && !$day->isAfter($this->last);
    }
}
