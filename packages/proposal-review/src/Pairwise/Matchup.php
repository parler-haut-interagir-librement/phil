<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Pairwise;

/**
 * Two proposals to show a voter side by side.
 */
final readonly class Matchup
{
    public function __construct(
        public string $left,
        public string $right,
    ) {
    }
}
