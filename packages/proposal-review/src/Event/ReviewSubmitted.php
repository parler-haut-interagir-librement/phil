<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Event;

/**
 * A reviewer scored a proposal, or scored it again. It names them only; a listener reads the rest from the store.
 */
final readonly class ReviewSubmitted
{
    public function __construct(
        public string $campaign,
        public string $proposal,
        public string $reviewer,
    ) {
    }
}
