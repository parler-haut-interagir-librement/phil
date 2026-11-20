<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Training;

/**
 * Who a course is assigned to: one learner, the members of a group, or the members of a unit of an organization. The
 * members of a group or a unit are named by an `AssigneeMembers` adapter of the application.
 */
enum AssigneeKind: string
{
    case Learner = 'learner';
    case Group = 'group';
    case Unit = 'unit';
}
