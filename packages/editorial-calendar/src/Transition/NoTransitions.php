<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Transition;

use DateTimeImmutable;
use Phil\EditorialCalendar\Contract\TransitionReader;

/**
 * The reader of a calendar that shows its own items only.
 */
final readonly class NoTransitions implements TransitionReader
{
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): iterable
    {
        return [];
    }
}
