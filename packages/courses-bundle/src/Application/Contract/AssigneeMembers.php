<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\Training\AssigneeKind;

/**
 * The members of a group or of a unit of an organization a course is assigned to, named by the application: the
 * bundle keeps no groups nor organizations. Each adapter answers for the kinds it knows; the bundle collects every
 * service tagged `phil_courses.assignee_members`, the first that supports a kind answering for it.
 */
interface AssigneeMembers
{
    public const string TAG = 'phil_courses.assignee_members';

    public function supports(AssigneeKind $kind): bool;

    /**
     * The stable identifiers of the learners who are members of that group or unit, read lazily.
     *
     * @return iterable<string>
     */
    public function members(AssigneeKind $kind, string $assignee): iterable;
}
