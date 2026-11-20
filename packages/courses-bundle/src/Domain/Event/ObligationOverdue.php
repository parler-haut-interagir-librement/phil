<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A learner's obligation under an assignment is overdue: told once a cycle. Identifiers only: a listener reads the rest
 * from the stores.
 */
final readonly class ObligationOverdue
{
    public function __construct(
        public string $assignment,
        public string $learner,
        public string $course,
    ) {
    }
}
