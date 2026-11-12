<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\CalendarSync\Exception\InvalidSync;

/**
 * A time when someone is taken, read from an external calendar: its start and its end, in UTC, and whether it is only
 * tentative. Nothing else: never a title, a place, a note or a guest, so that what the site keeps of another calendar
 * says when, never what.
 */
final readonly class BusyPeriod
{
    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    /**
     * @throws InvalidSync when the end is not after the start
     */
    public function __construct(DateTimeInterface $start, DateTimeInterface $end, public bool $tentative = false)
    {
        $utc = new DateTimeZone('UTC');
        $this->start = DateTimeImmutable::createFromInterface($start)->setTimezone($utc);
        $this->end = DateTimeImmutable::createFromInterface($end)->setTimezone($utc);

        if ($this->end <= $this->start) {
            throw InvalidSync::because('A busy period ends after it starts.');
        }
    }

    /**
     * Whether the two share a moment; a period that ends when the other starts does not.
     */
    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * The part of the period inside a window, or `null` when none is.
     */
    public function within(SyncWindow $window): ?self
    {
        $start = max($this->start, $window->from);
        $end = min($this->end, $window->to);

        return $end > $start ? new self($start, $end, $this->tentative) : null;
    }
}
