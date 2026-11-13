<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Fixture;

use Override;
use Phil\PollBundle\Application\Contract\PollPolicy;
use Phil\PollBundle\Domain\Model\Poll;
use Phil\Resource\ResourceReference;

/**
 * A policy whose answers the test sets: who may create, who may vote, who manages.
 */
final class Policy implements PollPolicy
{
    /**
     * @param list<string> $refusedVoters
     * @param list<string> $managers
     */
    public function __construct(
        public bool $creates = true,
        public array $refusedVoters = [],
        public array $managers = ['user:boss'],
    ) {
    }

    #[Override]
    public function mayCreate(ResourceReference $actor, ?ResourceReference $subject): bool
    {
        return $this->creates;
    }

    #[Override]
    public function mayVote(Poll $poll, ResourceReference $voter): bool
    {
        return !\in_array($voter->toString(), $this->refusedVoters, true);
    }

    #[Override]
    public function mayManage(Poll $poll, ?ResourceReference $actor): bool
    {
        return $actor instanceof ResourceReference && \in_array($actor->toString(), $this->managers, true);
    }
}
