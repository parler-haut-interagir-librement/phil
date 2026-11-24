<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Override;
use Phil\FollowBundle\Application\Contract\SubjectParents;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Subjects without parents: a follow covers its own subject only.
 */
final readonly class NoParents implements SubjectParents
{
    #[Override]
    public function parentsOf(SubjectReference $subject): iterable
    {
        return [];
    }
}
