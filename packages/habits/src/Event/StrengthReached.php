<?php

declare(strict_types=1);

namespace Phil\Habits\Event;

/**
 * A check-in raised a habit's strength to or past one of the percentages the application celebrates. A fall below it
 * dispatches nothing. It names the habit, its member and the percentage only.
 */
final readonly class StrengthReached
{
    public function __construct(
        public string $habit,
        public string $member,
        public int $percent,
    ) {
    }
}
