<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner's completion under an assignment is renewed: a new cycle is open, and their progress in the course starts
 * again. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class ObligationRenewed
{
    public function __construct(
        public string $assignment,
        public string $learner,
        public string $course,
    ) {
    }
}
