<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Pairwise;

/**
 * A proposal's place in the pairwise votes: its Bradley–Terry strength, on a log scale where 0 is the model's reference
 * proposal — what a proposal without votes scores —, and the votes behind it.
 */
final readonly class PairwiseScore
{
    public function __construct(
        public string $proposal,
        public float $strength,
        public int $wins,
        public int $losses,
    ) {
    }

    public function comparisons(): int
    {
        return $this->wins + $this->losses;
    }

    /**
     * The chance the model gives this proposal to be preferred to another: 0.5 between equals.
     */
    public function chanceAgainst(self $other): float
    {
        return 1 / (1 + exp($other->strength - $this->strength));
    }

    /**
     * The strength on the scale of chess ratings, 1 500 for the reference, 400 points for ten-to-one odds.
     */
    public function rating(): float
    {
        return 1500 + 400 * $this->strength / log(10);
    }
}
