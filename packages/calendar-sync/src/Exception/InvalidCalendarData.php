<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Exception;

use RuntimeException;

/**
 * What a calendar sent cannot be read: it is not iCalendar, nor the XML or JSON its service writes, or it is larger
 * than the package reads. The message names what was wrong, never what was read.
 */
final class InvalidCalendarData extends RuntimeException implements CalendarSyncException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
