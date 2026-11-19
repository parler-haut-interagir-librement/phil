<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * The items that cover the same days: one day, or from a first to a last.
 */
final readonly class AgendaGroup
{
    /**
     * @param list<Item> $items
     */
    public function __construct(
        public DateTimeImmutable $first,
        public DateTimeImmutable $last,
        public array $items,
    ) {
    }
}
