<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use DateTimeInterface;
use Phil\CalendarSync\ICalendar\ICalendarWriter;
use Phil\CalendarSync\OutboundEvent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * The date chosen as an iCalendar file (RFC 5545), written by the writer of `phil/calendar-sync`: one event, its start
 * and end in UTC, the poll's title, description and place — within the bounds of an `OutboundEvent` by those of a poll —, and an identifier stable for the poll, so that a calendar
 * that imports the file twice keeps one event.
 */
final readonly class ChosenDateCalendar
{
    public const string PRODUCT = '-//PHIL//phil/scheduling-poll-bundle//EN';

    public const string UID_PREFIX = 'phil-scheduling-poll-';

    public static function file(SchedulingPoll $poll, Slot $slot, DateTimeInterface $stamp): string
    {
        $event = new OutboundEvent(
            self::UID_PREFIX.$poll->id,
            $poll->title(),
            $slot->startsAt($poll->timezone()),
            $slot->endsAt($poll->timezone()),
            description: $poll->description(),
            location: $poll->location(),
        );

        return ICalendarWriter::event($event, $stamp, self::PRODUCT);
    }
}
