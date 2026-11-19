<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * A habit's progress on a day: its streaks, its strength from 0 to 1, and the period that holds the day.
 */
final readonly class Progress
{
    public function __construct(
        public Streak $streak,
        public float $strength,
        public ?PeriodProgress $current,
    ) {
    }

    /**
     * The strength as a whole percentage, from 0 to 100, the way it is shown beside the streaks.
     *
     * @return int<0, 100>
     */
    public function strengthPercent(): int
    {
        $percent = (int) round($this->strength * 100);
        \assert($percent >= 0 && $percent <= 100);

        return $percent;
    }
}
