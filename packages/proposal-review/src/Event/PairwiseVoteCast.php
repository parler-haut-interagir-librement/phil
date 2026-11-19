<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Event;

/**
 * A vote between two proposals was added to a campaign's log.
 */
final readonly class PairwiseVoteCast
{
    public function __construct(
        public string $campaign,
        public int $sequence,
    ) {
    }
}
