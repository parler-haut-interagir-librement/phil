<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * Where a period stands: the units done out of the units it asks — check-ins for a habit done or not, the sum of the
 * values towards the target otherwise —, and its days skipped or frozen.
 */
final readonly class PeriodProgress
{
    public function __construct(
        public Period $period,
        public int $done,
        public int $required,
        public int $excusedDays,
    ) {
    }

    public function isMet(): bool
    {
        return $this->done >= $this->required;
    }

    /**
     * How much of the period was done, from 0 to 1.
     */
    public function completion(): float
    {
        return min(1.0, $this->done / $this->required);
    }
}
