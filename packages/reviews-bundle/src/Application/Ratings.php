<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Application;

use Phil\ReviewsBundle\Domain\Contract\RatingRepository;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;

/**
 * The ratings of the subjects: read as last computed, computed again after each change of their reviews, and ranked by
 * weighted average under the prior of their type.
 */
final readonly class Ratings
{
    public function __construct(
        private RatingRepository $ratings,
        private SubjectTypes $types,
    ) {
    }

    public function summary(SubjectRef $subject): RatingSummary
    {
        return $this->ratings->summary($subject);
    }

    /**
     * @throws InvalidReview when the type of the subject is not declared
     */
    public function recompute(SubjectRef $subject): RatingSummary
    {
        return $this->ratings->recompute($subject, $this->types->get($subject->type)->prior);
    }

    /**
     * Computes again the ratings of every subject of the type, or of every declared type; how many.
     *
     * @throws InvalidReview when the type is not declared
     */
    public function recomputeAll(?string $type = null): int
    {
        $count = 0;

        foreach (null === $type ? $this->types->all() : [$this->types->get($type)] as $declared) {
            foreach ($this->ratings->subjects($declared->name) as $subject) {
                $this->ratings->recompute($subject, $declared->prior);
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @throws InvalidReview when the type of the subject is not declared
     */
    public function suspend(SubjectRef $subject, bool $suspended): void
    {
        $this->types->get($subject->type);
        $this->ratings->suspend($subject, $suspended);
    }

    /**
     * The subjects of the type by decreasing weighted average.
     *
     * @param positive-int $limit
     *
     * @return list<RatingSummary>
     *
     * @throws InvalidReview when the type is not declared
     */
    public function ranking(string $type, int $limit): array
    {
        $this->types->get($type);

        return $this->ratings->ranking($type, $limit);
    }
}
