<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Event;

/**
 * A scheduling poll reached its deadline and nobody could come to any of its dates: it is closed without one, and
 * nothing is sent to its respondents. Dispatched once, by the closing that won.
 */
final readonly class ClosedWithoutDate
{
    public function __construct(
        public string $poll,
    ) {
    }
}
