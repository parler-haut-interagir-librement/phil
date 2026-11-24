<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Event;

use Phil\SchedulingPollBundle\Domain\ClosedBy;

/**
 * A scheduling poll was closed on a date, by its organizer or at its deadline: dispatched once, by the closing that
 * won, after the announcement to the respondents was queued.
 */
final readonly class DateChosen
{
    public function __construct(
        public string $poll,
        public string $slot,
        public ClosedBy $closedBy,
    ) {
    }
}
