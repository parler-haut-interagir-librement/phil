<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * What a member did with a habit on a day.
 */
enum CheckInStatus: string
{
    /** Done, the whole target when the habit has one. */
    case Done = 'done';

    /** Done in part: a value towards the habit's numeric target. */
    case Partial = 'partial';

    /** Skipped on purpose: the day neither counts nor breaks a streak. */
    case Skipped = 'skipped';

    /** Frozen ahead of time — a trip, an illness, a rest day —: the day neither counts nor breaks a streak. */
    case Frozen = 'frozen';

    /**
     * Whether the day is excused: kept out of streaks and strength, and counted towards a period's check-ins only to
     * spare its streak.
     */
    public function isExcused(): bool
    {
        return self::Skipped === $this || self::Frozen === $this;
    }
}
