<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Path;

use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\Path\StepState;

/**
 * Where a learner stands in a step of a learning path: the step, its state, and the completion that completed it —
 * of the course itself, or of an equivalent course.
 */
final readonly class StepStatus
{
    public function __construct(
        public PathStep $step,
        public StepState $state,
        public ?Completion $completion = null,
    ) {
    }
}
