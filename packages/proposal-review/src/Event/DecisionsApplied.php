<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Event;

/**
 * The pending decisions of a campaign were applied together: the moment to tell the authors.
 */
final readonly class DecisionsApplied
{
    /**
     * @param list<string> $proposals the proposals whose decision was applied, in identifier order
     */
    public function __construct(
        public string $campaign,
        public array $proposals,
    ) {
    }
}
