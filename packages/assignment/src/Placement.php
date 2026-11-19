<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * Where an applicant was placed, and why: by what, at which round, at which rank of their choices.
 */
final readonly class Placement
{
    /**
     * @param positive-int|null $rank  the rank of the slot among the applicant's choices, from 1; null for a forced slot not ranked
     * @param positive-int|null $round the round of ranked choices that placed the applicant, from 1; null outside the rounds
     */
    public function __construct(
        public string $applicant,
        public string $slot,
        public PlacedBy $placedBy,
        public ?int $rank,
        public ?int $round = null,
    ) {
    }
}
