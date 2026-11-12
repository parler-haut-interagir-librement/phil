<?php

declare(strict_types=1);

namespace Phil\CalendarSync\ICalendar;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The times of one `VEVENT`, nothing else: its start, its end or its duration, its rule and its added and removed
 * dates, the occurrence it changes, and whether it blocks time, tentatively or not.
 *
 * @internal
 */
final readonly class EventTimes
{
    /**
     * @param ?array{int, int}        $duration     days, counted on the wall clock, and seconds
     * @param list<DateTimeImmutable> $added
     * @param list<DateTimeImmutable> $removed
     * @param ?string                 $recurrenceId the instant, in UTC, of the occurrence it changes
     */
    public function __construct(
        public ?string $uid,
        public DateTimeImmutable $start,
        public bool $isDate,
        public ?DateTimeImmutable $end,
        public ?array $duration,
        public ?string $rule,
        public array $added,
        public array $removed,
        public ?string $recurrenceId,
        public bool $blocks,
        public bool $tentative,
    ) {
    }

    /**
     * Whether the event has a single occurrence: no rule, no date added or removed.
     */
    public function isSingle(): bool
    {
        return null === $this->rule && [] === $this->added && [] === $this->removed;
    }

    /**
     * The end of an occurrence: the event's end as many days and seconds after its start, or its duration, or the day
     * after for a whole day; a time without either ends when it starts, and gives no period.
     */
    public function endOf(DateTimeImmutable $start): DateTimeImmutable
    {
        if ($this->end instanceof DateTimeImmutable) {
            $utc = new DateTimeZone('UTC');
            $days = (int) new DateTimeImmutable($this->start->format('Y-m-d'), $utc)->diff(new DateTimeImmutable($this->end->setTimezone($this->start->getTimezone())->format('Y-m-d'), $utc))->format('%r%a');
            $seconds = $this->end->getTimestamp() - $this->start->modify(\sprintf('%+d days', $days))->getTimestamp();

            return $start->modify(\sprintf('%+d days', $days))->modify(\sprintf('%+d seconds', $seconds));
        }

        if (null !== $this->duration) {
            return $start->modify(\sprintf('+%d days', $this->duration[0]))->modify(\sprintf('+%d seconds', $this->duration[1]));
        }

        return $this->isDate ? $start->modify('+1 day') : $start;
    }
}
