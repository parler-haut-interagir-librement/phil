<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeImmutable;

/**
 * Where a view's items come from: the calendar's events, the almanac's dates, an application's own.
 */
interface ItemSource
{
    /**
     * The items that touch a period, its end excluded.
     *
     * @return list<Item>
     */
    public function items(DateTimeImmutable $from, DateTimeImmutable $until): array;
}
