<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Override;
use Phil\FollowBundle\Application\Contract\FollowAccess;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Every member may follow every subject: the choice of an application whose subjects are all public. An application
 * with private subjects names a `FollowAccess` of its own.
 */
final readonly class OpenAccess implements FollowAccess
{
    #[Override]
    public function filter(SubjectReference $subject, array $members): array
    {
        return $members;
    }
}
