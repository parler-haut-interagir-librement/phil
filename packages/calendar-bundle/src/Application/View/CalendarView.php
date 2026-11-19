<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * The ways the calendar shows a period.
 */
enum CalendarView: string
{
    /** The weeks of a month, in a grid. */
    case Month = 'month';

    /** Seven days, by the hour. */
    case Week = 'week';

    /** One day, by the hour. */
    case Day = 'day';

    /** The dates of a month, grouped by the days they cover. */
    case List = 'list';

    /** The events of a month, as posters. */
    case Posters = 'posters';
}
