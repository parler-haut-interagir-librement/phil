<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application;

use Override;
use Phil\FollowBundle\Application\Contract\FollowApproval;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Every subject open: a member follows at once, without a request.
 */
final readonly class OpenFollowing implements FollowApproval
{
    #[Override]
    public function approverOf(SubjectReference $subject, string $follower): ?string
    {
        return null;
    }
}
