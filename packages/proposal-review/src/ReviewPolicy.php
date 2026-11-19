<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Exception\InvalidInput;

/**
 * How a campaign reviews: blind or not, how many reviews a proposal needs and may have, how scores combine, when a
 * reviewer sees the others' reviews, and whether a reviewer reviews only what they were assigned.
 */
final readonly class ReviewPolicy
{
    public const int MAX_REVIEWS = 1_000;

    /**
     * @throws InvalidInput
     */
    public function __construct(
        public bool $blind = true,
        public int $minReviews = 3,
        public ?int $maxReviews = null,
        public Aggregation $aggregation = Aggregation::Mean,
        public Visibility $visibility = Visibility::AfterOwnReview,
        public bool $assignedOnly = true,
    ) {
        if ($minReviews < 1 || $minReviews > self::MAX_REVIEWS) {
            throw InvalidInput::because(\sprintf('A proposal needs 1 to %d reviews.', self::MAX_REVIEWS));
        }

        if (null !== $maxReviews && ($maxReviews < $minReviews || $maxReviews > self::MAX_REVIEWS)) {
            throw InvalidInput::because(\sprintf('The most reviews of a proposal is its minimum to %d.', self::MAX_REVIEWS));
        }
    }
}
