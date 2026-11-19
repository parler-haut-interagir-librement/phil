<?php

declare(strict_types=1);

namespace Phil\ProposalReview;

use Phil\ProposalReview\Exception\InvalidInput;

/**
 * The arithmetic of scores, apart from any store: a review's weighted score, and the score of several reviews. Each
 * criterion's score is brought to 0–1 on its own scale, so that a criterion out of 10 does not outweigh one out of 5;
 * the weighted average of those makes the review's score, from 0 to 1.
 */
final readonly class Scoring
{
    /**
     * Checks that a review scores every criterion, once, within its scale.
     *
     * @param non-empty-list<Criterion> $criteria
     * @param list<Score>               $scores
     *
     * @throws InvalidInput
     */
    public static function check(array $criteria, array $scores): void
    {
        $given = array_map(static fn (Score $score): string => $score->criterion, $scores);

        if (\count(array_unique($given)) !== \count($given)) {
            throw InvalidInput::because('A review scores each criterion once.');
        }

        foreach ($criteria as $criterion) {
            $score = array_find($scores, static fn (Score $score): bool => $score->criterion === $criterion->id);

            if (null === $score) {
                throw InvalidInput::because(\sprintf('The review does not score criterion "%s".', $criterion->id));
            }

            if ($score->value < $criterion->min || $score->value > $criterion->max) {
                throw InvalidInput::because(\sprintf('The score of criterion "%s" is %d to %d.', $criterion->id, $criterion->min, $criterion->max));
            }
        }

        if (\count($scores) !== \count($criteria)) {
            throw InvalidInput::because('The review scores a criterion the campaign does not have.');
        }
    }

    /**
     * The score of one review, from 0 to 1.
     *
     * @param non-empty-list<Criterion> $criteria
     * @param list<Score>               $scores   checked by `check()`
     */
    public static function review(array $criteria, array $scores): float
    {
        $total = 0.0;
        $weights = 0;

        foreach ($criteria as $criterion) {
            $score = array_find($scores, static fn (Score $score): bool => $score->criterion === $criterion->id);

            if (null !== $score) {
                $total += $criterion->weight * $criterion->normalize($score->value);
                $weights += $criterion->weight;
            }
        }

        return 0 === $weights ? 0.0 : $total / $weights;
    }

    /**
     * The score of several reviews; null for none.
     *
     * @param list<float> $scores
     */
    public static function aggregate(array $scores, Aggregation $aggregation): ?float
    {
        $count = \count($scores);

        if (0 === $count) {
            return null;
        }

        if (Aggregation::Mean === $aggregation) {
            return array_sum($scores) / $count;
        }

        sort($scores);
        $middle = intdiv($count, 2);

        return 1 === $count % 2 ? $scores[$middle] : ($scores[$middle - 1] + $scores[$middle]) / 2;
    }
}
