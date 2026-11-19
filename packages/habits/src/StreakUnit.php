<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * What a streak counts: the periods of the habit's schedule.
 */
enum StreakUnit: string
{
    /** Days the habit was due on: every day, or some days of the week. */
    case Day = 'day';

    /** Windows of N days. */
    case Interval = 'interval';

    /** ISO weeks. */
    case Week = 'week';

    /** Calendar months. */
    case Month = 'month';
}
