<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Exception;

/**
 * A text refused whole by the reader: `InvalidCalendar` when it is not iCalendar or built wrongly, `LimitExceeded`
 * when it passes a bound. One `catch` covers both.
 */
interface UnreadableCalendar extends CalendarImportException
{
}
