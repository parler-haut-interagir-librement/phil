<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Contract;

use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * Where the ratings of each subject are kept beside its reviews, so that a page or a ranking reads them without
 * counting the reviews again.
 */
interface RatingRepository
{
    /**
     * The ratings last computed, or empty ones for a subject nothing was computed of.
     */
    public function summary(SubjectRef $subject): RatingSummary;

    /**
     * Computes the ratings again from the published reviews and keeps them. Idempotent: computing twice gives the same
     * ratings; two computations at the same moment are serialised on the subject, each reading the reviews once it holds
     * it, so that the last one written never misses a review written before it started.
     */
    public function recompute(SubjectRef $subject, BayesianPrior $prior): RatingSummary;

    /**
     * Suspends or resumes the new reviews of the subject; its reviews and ratings stay.
     */
    public function suspend(SubjectRef $subject, bool $suspended): void;

    /**
     * The subjects of the type that have reviews, by decreasing weighted average.
     *
     * @param positive-int $limit
     *
     * @return list<RatingSummary>
     */
    public function ranking(string $type, int $limit): array;

    /**
     * Every subject of the type that has reviews or ratings, to compute them again after a change of the prior.
     *
     * @return iterable<SubjectRef>
     */
    public function subjects(string $type): iterable;
}
