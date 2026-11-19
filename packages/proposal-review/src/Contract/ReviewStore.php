<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Contract;

use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Review;

/**
 * Where reviews and decisions are kept.
 */
interface ReviewStore
{
    /**
     * Keeps a review, replacing the one the same reviewer gave the same proposal.
     */
    public function save(Review $review): void;

    /**
     * The reviews of a proposal, in reviewer identifier order.
     *
     * @return list<Review>
     */
    public function ofProposal(string $campaign, string $proposal): array;

    /**
     * Keeps a decision, replacing the one the proposal had.
     */
    public function saveDecision(DecisionRecord $decision): void;

    /**
     * The decisions of a campaign, in proposal identifier order.
     *
     * @return list<DecisionRecord>
     */
    public function decisions(string $campaign): array;
}
