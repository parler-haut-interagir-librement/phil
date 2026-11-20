<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner's enrollment starts again: its progress forgotten and its completion undone, for a recertification.
 * Identifiers only: a listener reads the rest from the stores.
 */
final readonly class EnrollmentRenewed
{
    public function __construct(
        public string $enrollment,
        public string $course,
        public string $learner,
    ) {
    }
}
