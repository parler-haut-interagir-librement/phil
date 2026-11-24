<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Contract;

use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Who may follow a subject and hear of its activity: asked when a member follows it, and again for each page of
 * followers an activity is sent to, so that a member who lost access to a private subject hears nothing more of it.
 */
interface FollowAccess
{
    /**
     * @param list<string> $members
     *
     * @return list<string> the members allowed, in the order given
     */
    public function filter(SubjectReference $subject, array $members): array;
}
