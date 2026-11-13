<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application;

use Override;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

/**
 * The policy of an application that names none: everyone known may create a poll and vote, and only the creator of
 * a poll manages it.
 */
final readonly class DefaultPollPolicy implements PollPolicy
{
    #[Override]
    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool
    {
        return true;
    }

    #[Override]
    public function mayVote(Poll $poll, ResourceReference $voter): bool
    {
        return true;
    }

    #[Override]
    public function mayManage(Poll $poll, ?ResourceReference $actor): bool
    {
        return $actor instanceof ResourceReference && $poll->isCreatedBy($actor);
    }
}
