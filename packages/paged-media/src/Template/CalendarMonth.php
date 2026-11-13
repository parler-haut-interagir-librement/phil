<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PagedMedia\Exception\InvalidDocument;

/**
 * One month of a printed calendar: its year and month, its title as the reader reads it — "November 2026",
 * "novembre 2026" —, and what happens each day, by day of the month.
 */
final readonly class CalendarMonth
{
    /**
     * @param array<int, list<string>> $events what each day holds, by day of the month
     */
    public function __construct(
        public int $year,
        public int $month,
        public string $title,
        public array $events = [],
    ) {
        if ($year < 1 || $year > 9999 || $month < 1 || $month > 12) {
            throw InvalidDocument::because(\sprintf('A calendar month is a month from 1 to 12 of a year from 1 to 9999; %d-%d is not.', $year, $month));
        }

        $days = $this->days();

        foreach (array_keys($events) as $day) {
            if ($day < 1 || $day > $days) {
                throw InvalidDocument::because(\sprintf('The events of %s fall on days from 1 to %d; %d is not.', $title, $days, $day));
            }
        }
    }

    /**
     * The number of days of the month.
     */
    public function days(): int
    {
        return (int) $this->first()->format('t');
    }

    /**
     * The month's first day.
     */
    public function first(): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('%04d-%02d-01', $this->year, $this->month), new DateTimeZone('UTC'));
    }
}
