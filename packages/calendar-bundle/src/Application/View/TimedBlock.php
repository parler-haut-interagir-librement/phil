<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * An item at its time in a day's column: where it starts and how long it lasts, in percent of the hours shown, and its
 * place among those it overlaps — lane `lane` of `lanes`.
 */
final readonly class TimedBlock
{
    public function __construct(
        public Item $item,
        public float $top,
        public float $height,
        public int $lane,
        public int $lanes,
        public bool $continuesBefore,
        public bool $continuesAfter,
    ) {
    }
}
