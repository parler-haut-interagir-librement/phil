<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Application\Contract\AssigneeMembers;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;

/**
 * The members of the groups, by group, as a test sets them; units are not known.
 */
final class Members implements AssigneeMembers
{
    /** @var array<string, list<string>> */
    public array $groups = [];

    #[Override]
    public function supports(AssigneeKind $kind): bool
    {
        return AssigneeKind::Group === $kind;
    }

    #[Override]
    public function members(AssigneeKind $kind, string $assignee): iterable
    {
        yield from $this->groups[$assignee] ?? [];
    }
}
