<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

/**
 * The average score of one criterion over the published reviews that rated it.
 */
final readonly class CriterionRating
{
    public function __construct(
        public string $criterion,
        public float $mean,
        public int $count,
    ) {
    }
}
