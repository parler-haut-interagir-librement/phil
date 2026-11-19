<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * One row of a month's grid: its seven days, the bars that fit in its lanes, and, for each day, the items that did not.
 */
final readonly class MonthWeek
{
    /**
     * @param list<DateTimeImmutable> $days
     * @param list<Bar>               $bars
     * @param array<int, list<Item>>  $hidden by column
     */
    public function __construct(
        public array $days,
        public array $bars,
        public array $hidden,
    ) {
    }

    /**
     * The bars that start in a lane, by column: what a row of the grid draws.
     *
     * @return list<Bar>
     */
    public function lane(int $lane): array
    {
        return array_values(array_filter($this->bars, static fn (Bar $bar): bool => $bar->lane === $lane));
    }

    /**
     * How many lanes its bars use.
     */
    public function lanes(): int
    {
        return [] === $this->bars ? 0 : max(array_map(static fn (Bar $bar): int => $bar->lane, $this->bars)) + 1;
    }
}
