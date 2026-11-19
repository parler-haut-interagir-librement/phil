<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * One column of a week or a day: a day, and a resource when the day is shared, with its whole-day items and its
 * timed ones.
 */
final readonly class TimeColumn
{
    /**
     * @param list<Item>       $allDay
     * @param list<TimedBlock> $blocks
     */
    public function __construct(
        public DateTimeImmutable $day,
        public ?CalendarResource $resource,
        public array $allDay,
        public array $blocks,
    ) {
    }
}
