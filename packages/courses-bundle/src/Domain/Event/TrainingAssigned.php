<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A course is assigned to a learner, a group or a unit. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class TrainingAssigned
{
    public function __construct(
        public string $assignment,
        public string $course,
    ) {
    }
}
