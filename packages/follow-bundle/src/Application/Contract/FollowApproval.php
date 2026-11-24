<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Contract;

use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Who approves a new follower of a subject: the member of a locked profile, the owner of a private group. Null leaves
 * the subject open, followed at once.
 */
interface FollowApproval
{
    public function approverOf(SubjectReference $subject, string $follower): ?string;
}
