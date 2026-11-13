<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Contract;

use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

/**
 * What the people of the application may do with polls: create them, vote in them, manage them. The application's
 * roles, memberships and invitations decide; the bundle only asks.
 */
interface PollPolicy
{
    /**
     * Whether the actor may create a poll, about this subject when it has one.
     */
    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool;

    /**
     * Whether the voter may cast, change or withdraw a ballot in the poll while it is open.
     */
    public function mayVote(Poll $poll, ResourceReference $voter): bool;

    /**
     * Whether the actor — `null` for someone not signed in — may close, reschedule and delete the poll, and see its
     * results whoever else may.
     */
    public function mayManage(Poll $poll, ?ResourceReference $actor): bool;
}
