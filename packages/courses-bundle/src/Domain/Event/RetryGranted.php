<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A second chance was granted: the learner has one more try. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class RetryGranted
{
    public function __construct(
        public string $request,
        public string $enrollment,
        public string $activity,
        public string $decidedBy,
    ) {
    }
}
