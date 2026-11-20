<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\ObligationState;

/**
 * An obligation as the pages show it: the course, the obligation, and where the learner stands.
 */
final readonly class TrainingStatus
{
    public function __construct(
        public Course $course,
        public Obligation $obligation,
        public ObligationState $state,
    ) {
    }
}
