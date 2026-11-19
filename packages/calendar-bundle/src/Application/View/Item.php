<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * What a view shows: an occurrence of an event, or a date of the almanac, with what the views need of it. Whole days
 * end on the midnight of their last day; a time ends at its instant.
 */
final readonly class Item
{
    public const string EVENT = 'event';

    public const string ALMANAC = 'almanac';

    /**
     * @param list<string> $tags
     * @param list<string> $hosts as `kind:reference`
     * @param ?string      $venue the identifier of its venue
     * @param ?string      $place where it takes place, in words: the venue's label, or the place said
     */
    public function __construct(
        public string $id,
        public string $title,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public bool $allDay,
        public string $source = self::EVENT,
        public ?string $url = null,
        public array $tags = [],
        public array $hosts = [],
        public ?string $venue = null,
        public ?string $place = null,
        public ?string $image = null,
        public ?string $description = null,
        public bool $cancelled = false,
    ) {
    }

    /**
     * The first day it covers, at midnight in the time zone of its start.
     */
    public function firstDay(): DateTimeImmutable
    {
        return $this->start->setTime(0, 0);
    }

    /**
     * The last day it covers: a time that ends at midnight ends the day before.
     */
    public function lastDay(): DateTimeImmutable
    {
        $last = $this->end->setTime(0, 0);

        return !$this->allDay && $last->getTimestamp() === $this->end->getTimestamp() && $this->end > $this->start ? $last->modify('-1 day') : $last;
    }

    /**
     * The number of its first day, as `Period::dayNumber()` counts them.
     */
    public function firstDayNumber(): int
    {
        return Period::dayNumber($this->start);
    }

    /**
     * The number of its last day, as `Period::dayNumber()` counts them.
     */
    public function lastDayNumber(): int
    {
        return Period::dayNumber($this->lastDay());
    }

    /**
     * Whether it covers more than one day.
     */
    public function spansDays(): bool
    {
        return $this->firstDay()->format('Y-m-d') !== $this->lastDay()->format('Y-m-d');
    }
}
