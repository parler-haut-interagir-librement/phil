<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\ValueObject;

use Phil\ReviewsBundle\Domain\Exception\InvalidReview;

/**
 * The weighted average of a subject type, published with its parameters: `count` fictitious reviews of the neutral
 * value `mean` are added to the real ones, so that a subject with two perfect reviews does not rank above one with two
 * hundred good ones; and an average is shown only from `minimum` reviews.
 *
 *     weighted = (mean × count + sum of the scores) / (count + number of reviews)
 */
final readonly class BayesianPrior
{
    public const int MAX_COUNT = 10_000;

    /**
     * @throws InvalidReview when a count is negative or too large, or the mean is outside the scale
     */
    public function __construct(
        public int $count,
        public float $mean,
        public int $minimum,
    ) {
        if ($count < 0 || $count > self::MAX_COUNT || $minimum < 0 || $minimum > self::MAX_COUNT) {
            throw InvalidReview::because(\sprintf('The counts of a weighted average are from 0 to %d.', self::MAX_COUNT));
        }

        if (!is_finite($mean) || $mean < 1.0 || $mean > ReviewContent::MAX_SCALE) {
            throw InvalidReview::because(\sprintf('The neutral value of a weighted average is from 1 to %d.', ReviewContent::MAX_SCALE));
        }
    }

    /**
     * The prior of a scale whose neutral value is its middle: 3 out of 5, 5.5 out of 10.
     */
    public static function neutral(int $scale, int $count, int $minimum): self
    {
        return new self($count, (1 + $scale) / 2, $minimum);
    }

    /**
     * The weighted average of the reviews whose scores add up to the sum; the neutral value when there are none and no
     * fictitious review either.
     */
    public function weigh(int $reviews, int $sum): float
    {
        $weight = $this->count + $reviews;

        return 0 === $weight ? $this->mean : ($this->mean * $this->count + $sum) / $weight;
    }

    /**
     * Whether the subject has enough reviews for its average to be shown.
     */
    public function shows(int $reviews): bool
    {
        return $reviews > 0 && $reviews >= $this->minimum;
    }
}
