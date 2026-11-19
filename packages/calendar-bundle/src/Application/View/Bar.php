<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * An item laid across the days of a row: the column it starts in, how many it covers, its lane, and whether it
 * started before the row or goes on after it.
 */
final readonly class Bar
{
    public function __construct(
        public Item $item,
        public int $column,
        public int $span,
        public int $lane,
        public bool $continuesBefore,
        public bool $continuesAfter,
    ) {
    }
}
