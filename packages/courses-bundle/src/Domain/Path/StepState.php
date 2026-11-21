<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Path;

/**
 * Where a learner stands in a step of a learning path: waiting for its prerequisites, open to them, or completed — by
 * the course itself or by an equivalent course.
 */
enum StepState: string
{
    case Locked = 'locked';
    case Open = 'open';
    case Completed = 'completed';
}
