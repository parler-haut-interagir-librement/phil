<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * The activity of a request for a second chance was waived: the course counts it satisfied. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class RetryWaived
{
    public function __construct(
        public string $request,
        public string $enrollment,
        public string $activity,
        public string $decidedBy,
    ) {
    }
}
