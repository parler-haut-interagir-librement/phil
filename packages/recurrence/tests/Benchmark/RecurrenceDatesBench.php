<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Benchmark;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceDates;
use Phil\Recurrence\RecurrenceRule;

/**
 * Every import of an `.ics` file reads the `RDATE` and `EXDATE` lines of its events, and every calendar view merges the
 * added dates into the occurrences of its window: a full line of dates, and a window of a series with many added dates.
 *
 * @Groups({"recurrence"})
 */
final readonly class RecurrenceDatesBench
{
    private string $line;

    private Recurrence $recurrence;

    private DateTimeImmutable $from;

    private DateTimeImmutable $through;

    public function __construct()
    {
        $zone = new DateTimeZone('America/Toronto');
        $start = new DateTimeImmutable('2026-01-05 19:00', $zone);
        $dates = array_map(static fn (int $day): DateTimeImmutable => $start->modify(\sprintf('+%d days 2 hours', $day)), range(0, RecurrenceDates::MAX_DATES - 1));
        $this->line = (string) RecurrenceDates::of('RDATE', $dates, timezone: $zone);
        $this->recurrence = Recurrence::of($start, RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=MO,WE'), [], $dates);
        $this->from = new DateTimeImmutable('2027-11-01 00:00', $zone);
        $this->through = new DateTimeImmutable('2027-11-30 23:59', $zone);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchFullLineRead(): void
    {
        RecurrenceDates::parse($this->line);
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchMonthWindowWithAddedDates(): void
    {
        $this->recurrence->between($this->from, $this->through);
    }
}
