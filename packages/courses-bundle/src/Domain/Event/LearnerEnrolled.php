<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner enrolled in a course. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class LearnerEnrolled
{
    public function __construct(
        public string $enrollment,
        public string $course,
        public string $learner,
    ) {
    }
}
