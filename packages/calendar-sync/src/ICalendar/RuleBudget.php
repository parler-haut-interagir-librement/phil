<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use Phil\CalendarSync\Exception\InvalidCalendarData;

/**
 * How many repeated events one read of a calendar expands, `ICalendarReader::MAX_RULES` at most, shared by every text
 * of one answer: the `calendar-data` of a CalDAV multistatus each count against the same budget.
 *
 * A rule is walked up to the bounds of `phil/recurrence` even when it gives no occurrence in the window, so each rule
 * costs time; a text of many such rules would otherwise hold a worker for long.
 */
final class RuleBudget
{
    private int $spent = 0;

    /**
     * @throws InvalidCalendarData past `ICalendarReader::MAX_RULES` rules
     */
    public function spend(): void
    {
        if (++$this->spent > ICalendarReader::MAX_RULES) {
            throw InvalidCalendarData::because(\sprintf('A calendar repeats %d events at most.', ICalendarReader::MAX_RULES));
        }
    }
}
