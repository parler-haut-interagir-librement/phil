<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Contract;

use DateTimeImmutable;
use Phil\ProposalReview\Pairwise\Vote;

/**
 * The append-only log of a campaign's pairwise votes: the scores are computed from it, and computed again from it to
 * check them. A store numbers the votes of a campaign from 1, in the order it appends them, without gaps; two votes
 * appended at once both get a number.
 */
interface VoteLog
{
    /**
     * Appends a vote and gives it back with its number.
     */
    public function append(string $campaign, string $voter, string $winner, string $loser, DateTimeImmutable $castAt): Vote;

    /**
     * The votes of a campaign, in the order of their numbers.
     *
     * @return list<Vote>
     */
    public function ofCampaign(string $campaign): array;
}
