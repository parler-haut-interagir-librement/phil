<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * A habit's streaks: the periods met in a row up to now, and the longest such run.
 *
 * A streak does not punish: a skipped or frozen day, and the period still running, neither add to it nor break it.
 * Only a period over and not met brings the current streak back to zero; the best one stays.
 */
final readonly class Streak
{
    public function __construct(
        public int $current,
        public int $best,
        public StreakUnit $unit,
    ) {
    }
}
