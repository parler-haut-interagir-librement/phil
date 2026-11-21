<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Path;

use Phil\CoursesBundle\Domain\Model\PathEnrollment;

/**
 * A learner's progress in a learning path: their enrollment, each step with its state in the order of the path, and
 * the share of the required steps completed, from 0 to 100.
 */
final readonly class PathProgress
{
    /**
     * @param list<StepStatus> $steps
     * @param int<0, 100>      $percent
     */
    public function __construct(
        public PathEnrollment $enrollment,
        public array $steps,
        public int $percent,
    ) {
    }
}
