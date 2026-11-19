<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Contract;

use DateTimeImmutable;
use Phil\EditorialCalendar\Transition\PlannedTransition;

/**
 * The port through which the calendar reads the transitions planned elsewhere — a page unpublished at a date, an event
 * opened at another — to show them beside its items. The application implements it over its scheduler.
 */
interface TransitionReader
{
    /**
     * The transitions planned from `$from`, included, to `$to`, excluded, both in UTC, in any order.
     *
     * @return iterable<PlannedTransition>
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): iterable;
}
