<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * When a computed date takes place: whole days, from the midnight of the first to that of the last, or an instant —
 * a full moon at 05:02 — whose start and end are the same.
 */
final readonly class DateSpan
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay,
    ) {
    }

    /**
     * How many days it covers: 1 for one day or an instant.
     */
    public function days(): int
    {
        return $this->allDay ? $this->start->diff($this->end)->days + 1 : 1;
    }

    /**
     * Whether it touches a period, its start included and its end not.
     */
    public function overlaps(DateTimeInterface $from, DateTimeInterface $until): bool
    {
        if (!$this->allDay) {
            return $this->start >= $from && $this->start < $until;
        }

        return $this->start < $until && $this->end->modify('+1 day') > $from;
    }
}
