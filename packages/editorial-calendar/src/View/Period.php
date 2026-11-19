<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\View;

use DateTimeImmutable;

/**
 * What a view shows: the UTC moments it starts at, included, and ends at, excluded, its entries in their order, and,
 * for a day, week or month, the same entries day by day.
 */
final readonly class Period
{
    /**
     * @param list<Entry> $entries
     * @param list<Day>   $days    empty for a list
     */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $entries,
        public array $days = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->entries;
    }
}
