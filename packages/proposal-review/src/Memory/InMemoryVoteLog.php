<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Memory;

use DateTimeImmutable;
use Override;
use Phil\ProposalReview\Contract\VoteLog;
use Phil\ProposalReview\Pairwise\Vote;

/**
 * Pairwise votes in memory, for tests and single runs; they last as long as the object.
 */
final class InMemoryVoteLog implements VoteLog
{
    /** @var array<string, list<Vote>> */
    private array $votes = [];

    #[Override]
    public function append(string $campaign, string $voter, string $winner, string $loser, DateTimeImmutable $castAt): Vote
    {
        $vote = new Vote($campaign, \count($this->votes[$campaign] ?? []) + 1, $voter, $winner, $loser, $castAt);
        $this->votes[$campaign][] = $vote;

        return $vote;
    }

    #[Override]
    public function ofCampaign(string $campaign): array
    {
        return $this->votes[$campaign] ?? [];
    }
}
