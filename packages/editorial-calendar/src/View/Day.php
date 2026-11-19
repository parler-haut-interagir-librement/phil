<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\View;

use DateTimeImmutable;

/**
 * One day of a view, in the time zone of the view: its date, the UTC moments it starts and ends at — 23 or 25 hours
 * apart on a change of time —, and its entries in their order.
 */
final readonly class Day
{
    /**
     * @param string      $date    the local date, `Y-m-d`
     * @param list<Entry> $entries
     */
    public function __construct(
        public string $date,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $entries,
    ) {
    }
}
