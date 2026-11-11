<?php

declare(strict_types=1);

namespace Phil\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Exception\InvalidInterval;

/**
 * A span of time, its start included and its end excluded: two spans that only touch do not overlap. Instants are
 * read to the second.
 */
final readonly class Interval
{
    public int $from;

    public int $until;

    /**
     * @throws InvalidInterval when the end does not come after the start
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        $this->from = $start->getTimestamp();
        $this->until = $end->getTimestamp();

        if ($this->until <= $this->from) {
            throw InvalidInterval::because(\sprintf('A span ends after it starts: %s does not come after %s.', $end->format(\DATE_ATOM), $start->format(\DATE_ATOM)));
        }
    }

    /**
     * A span between two Unix timestamps, given in a time zone.
     */
    public static function ofTimestamps(int $from, int $until, DateTimeZone $timezone): self
    {
        return new self(new DateTimeImmutable('@'.$from)->setTimezone($timezone), new DateTimeImmutable('@'.$until)->setTimezone($timezone));
    }

    /**
     * Its length, in seconds.
     */
    public function seconds(): int
    {
        return $this->until - $this->from;
    }

    public function overlaps(self $other): bool
    {
        return $this->from < $other->until && $other->from < $this->until;
    }

    /**
     * Whether it holds the other span whole.
     */
    public function covers(self $other): bool
    {
        return $this->from <= $other->from && $other->until <= $this->until;
    }

    /**
     * The span with some minutes added before its start and after its end.
     */
    public function widen(int $before, int $after): self
    {
        return 0 === $before && 0 === $after ? $this : new self($this->start->modify(\sprintf('-%d minutes', $before)), $this->end->modify(\sprintf('+%d minutes', $after)));
    }
}
