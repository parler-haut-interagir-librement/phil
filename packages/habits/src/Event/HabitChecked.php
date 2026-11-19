<?php

declare(strict_types=1);

namespace Phil\Habits\Event;

/**
 * A habit was checked in on a day: done, done in part, skipped or frozen. It names the habit and its member only; a listener reads the check-in from the store.
 */
final readonly class HabitChecked
{
    /**
     * @param string $day the member's day, as `YYYY-MM-DD`
     */
    public function __construct(
        public string $habit,
        public string $member,
        public string $day,
    ) {
    }
}
