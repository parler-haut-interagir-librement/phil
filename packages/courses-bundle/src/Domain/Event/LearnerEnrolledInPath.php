<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner enrolled in a learning path. Identifiers only: a listener reads the rest from
 * the stores.
 */
final readonly class LearnerEnrolledInPath
{
    public function __construct(
        public string $enrollment,
        public string $path,
        public string $learner,
    ) {
    }
}
