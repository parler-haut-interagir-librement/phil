<?php

declare(strict_types=1);

namespace Phil\Habits\Event;

/**
 * The check-in of a habit on a day was taken back. It names the habit and its member only.
 */
final readonly class HabitUnchecked
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
