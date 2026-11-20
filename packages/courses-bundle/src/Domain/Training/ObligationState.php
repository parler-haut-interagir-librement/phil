<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Training;

/**
 * Where a learner stands with a course assigned to them: still ahead of the reminder window, due soon, overdue, or
 * completed for the current cycle.
 */
enum ObligationState: string
{
    case Pending = 'pending';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Completed = 'completed';
}
