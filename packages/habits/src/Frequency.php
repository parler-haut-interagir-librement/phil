<?php

declare(strict_types=1);

namespace Phil\Habits;

/**
 * The kind of a schedule: what a period is, and how many check-ins it asks.
 */
enum Frequency: string
{
    /** Every day: one period per day, one check-in. */
    case Daily = 'daily';

    /** Once in every window of N days, counted from the habit's first day. */
    case EveryDays = 'every';

    /** On some days of the week: one period per such day, one check-in. */
    case Weekdays = 'on';

    /** N check-ins in each ISO week, Monday to Sunday. */
    case PerWeek = 'week';

    /** N check-ins in each calendar month. */
    case PerMonth = 'month';
}
