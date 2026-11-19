<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

/**
 * Where a proposal stands: its reviews, its score from 0 to 1 — null before its first review —, whether it has the
 * reviews the policy asks for, and its decision, pending or applied.
 */
final readonly class Standing
{
    public function __construct(
        public string $proposal,
        public int $reviews,
        public ?float $score,
        public bool $enoughReviews,
        public ?DecisionRecord $decision,
    ) {
    }
}
