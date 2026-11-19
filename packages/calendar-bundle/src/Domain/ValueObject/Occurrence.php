<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Model\Event;

/**
 * One time an event takes place: its start and end in the event's time zone. An all-day occurrence ends on the last
 * day it covers, included.
 */
final readonly class Occurrence
{
    public function __construct(
        public Event $event,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
    }

    /**
     * An identifier of the occurrence among the event's: the event's, and the start in UTC.
     */
    public function id(): string
    {
        return $this->event->id.'@'.$this->start->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
