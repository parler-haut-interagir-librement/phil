<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Pairwise;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Internal\Identifier;
use Phil\ProposalReview\Internal\Utc;

/**
 * One vote of the log: a voter preferred one proposal to another. Its number orders the log; its instant is in UTC.
 */
final readonly class Vote
{
    public string $campaign;

    public string $voter;

    public string $winner;

    public string $loser;

    public DateTimeImmutable $castAt;

    /**
     * @throws InvalidInput
     */
    public function __construct(
        string $campaign,
        public int $sequence,
        string $voter,
        string $winner,
        string $loser,
        DateTimeInterface $castAt,
    ) {
        $this->campaign = Identifier::check($campaign, 'campaign');
        $this->voter = Identifier::check($voter, 'voter');
        $this->winner = Identifier::check($winner, 'proposal');
        $this->loser = Identifier::check($loser, 'proposal');
        $this->castAt = Utc::of($castAt);

        if ($sequence < 1) {
            throw InvalidInput::because('The votes of a log are numbered from 1.');
        }

        if ($winner === $loser) {
            throw InvalidInput::because('A vote compares two different proposals.');
        }
    }
}
