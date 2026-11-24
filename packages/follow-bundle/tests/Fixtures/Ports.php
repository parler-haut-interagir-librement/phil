<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Fixtures;

use Override;
use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * The application's ports, set by the tests: parents by subject key, approvers by subject key, members refused, and
 * how many times each was asked.
 */
final class Ports implements SubjectParents, FollowApproval, FollowAccess
{
    /** @var array<string, list<SubjectReference>> */
    public array $parents = [];

    /** @var array<string, string> */
    public array $approvers = [];

    /** @var list<string> */
    public array $refused = [];

    public int $accessCalls = 0;

    #[Override]
    public function parentsOf(SubjectReference $subject): iterable
    {
        return $this->parents[$subject->key()] ?? [];
    }

    #[Override]
    public function approverOf(SubjectReference $subject, string $follower): ?string
    {
        return $this->approvers[$subject->key()] ?? null;
    }

    #[Override]
    public function filter(SubjectReference $subject, array $members): array
    {
        ++$this->accessCalls;

        return array_values(array_diff($members, $this->refused));
    }
}
