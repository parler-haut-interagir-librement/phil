<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

/**
 * The calendar periods a meter can count by.
 */
enum CalendarUnit: string
{
    case Day = 'day';
    /** An ISO 8601 week, from Monday. */
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
}
