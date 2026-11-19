<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use InvalidArgumentException;

/**
 * A month as weeks of seven days, each item a bar across the days it covers — split at the end of a week — in the
 * first lane free; past the lanes shown, an item counts in its days' "more".
 */
final readonly class MonthGrid
{
    /**
     * @param list<MonthWeek> $weeks
     */
    private function __construct(
        public Period $period,
        public array $weeks,
    ) {
    }

    /**
     * @param list<Item> $items
     * @param int        $lanes how many lanes a week shows
     */
    public static function of(Period $period, array $items, int $lanes = 3): self
    {
        if ($lanes < 1) {
            throw new InvalidArgumentException(\sprintf('A month shows at least one lane, not %d.', $lanes));
        }

        $weeks = [];
        // Each item's days counted once for every week: a week keeps the items it covers by comparing numbers.
        $spans = Lanes::spans($items);

        foreach (array_chunk($period->days(), 7) as $days) {
            $bars = Lanes::laid($spans, Period::dayNumber($days[0]), \count($days));
            $hidden = [];

            foreach ($bars as $bar) {
                if ($bar->lane >= $lanes) {
                    for ($column = $bar->column; $column < $bar->column + $bar->span; ++$column) {
                        $hidden[$column][] = $bar->item;
                    }
                }
            }

            // The bars come by first column: the columns of what is hidden come in order.
            $weeks[] = new MonthWeek($days, array_values(array_filter($bars, static fn (Bar $bar): bool => $bar->lane < $lanes)), $hidden);
        }

        return new self($period, $weeks);
    }
}
