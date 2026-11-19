<?php

declare(strict_types=1);

namespace Phil\Habits\Event;

use Phil\Habits\StreakUnit;

/**
 * A habit's current streak reached one of the lengths the application celebrates, for the first time in this run of
 * met periods. It names the habit, its member and the length only.
 */
final readonly class StreakReached
{
    public function __construct(
        public string $habit,
        public string $member,
        public int $length,
        public StreakUnit $unit,
    ) {
    }
}
