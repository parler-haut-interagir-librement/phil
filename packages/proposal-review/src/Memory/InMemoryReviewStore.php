<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Memory;

use Override;
use Phil\ProposalReview\Contract\ReviewStore;
use Phil\ProposalReview\DecisionRecord;
use Phil\ProposalReview\Review;

/**
 * Reviews and decisions in memory, for tests and single runs; they last as long as the object.
 */
final class InMemoryReviewStore implements ReviewStore
{
    /** @var array<string, array<string, array<string, Review>>> by campaign, proposal and reviewer */
    private array $reviews = [];

    /** @var array<string, array<string, DecisionRecord>> by campaign and proposal */
    private array $decisions = [];

    #[Override]
    public function save(Review $review): void
    {
        $this->reviews[$review->campaign][$review->proposal][$review->reviewer] = $review;
    }

    #[Override]
    public function ofProposal(string $campaign, string $proposal): array
    {
        $reviews = $this->reviews[$campaign][$proposal] ?? [];
        usort($reviews, static fn (Review $a, Review $b): int => strcmp($a->reviewer, $b->reviewer));

        return $reviews;
    }

    #[Override]
    public function saveDecision(DecisionRecord $decision): void
    {
        $this->decisions[$decision->campaign][$decision->proposal] = $decision;
    }

    #[Override]
    public function decisions(string $campaign): array
    {
        $decisions = $this->decisions[$campaign] ?? [];
        usort($decisions, static fn (DecisionRecord $a, DecisionRecord $b): int => strcmp($a->proposal, $b->proposal));

        return $decisions;
    }
}
