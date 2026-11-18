<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner out of tries asked for a second chance at an activity. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class RetryRequested
{
    public function __construct(
        public string $request,
        public string $enrollment,
        public string $activity,
    ) {
    }
}
