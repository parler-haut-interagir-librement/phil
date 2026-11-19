<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Event;

/**
 * A decision was recorded for a proposal, pending until the campaign's decisions are applied.
 */
final readonly class DecisionRecorded
{
    public function __construct(
        public string $campaign,
        public string $proposal,
    ) {
    }
}
