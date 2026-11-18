<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner satisfied every required activity of a course. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class CourseCompleted
{
    public function __construct(
        public string $enrollment,
        public string $course,
        public string $learner,
    ) {
    }
}
