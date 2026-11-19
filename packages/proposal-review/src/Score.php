<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Internal\Identifier;

/**
 * The score a review gives on one criterion.
 */
final readonly class Score
{
    public string $criterion;

    public function __construct(
        string $criterion,
        public int $value,
    ) {
        $this->criterion = Identifier::check($criterion, 'criterion');
    }
}
