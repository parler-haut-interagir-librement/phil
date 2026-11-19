<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit;

use Phil\ProposalReview\Aggregation;
use Phil\ProposalReview\Criterion;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Score;
use Phil\ProposalReview\Scoring;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scoring::class)]
final class ScoringTest extends TestCase
{
    public function testAReviewScoreIsTheWeightedAverageOfNormalizedScores(): void
    {
        $criteria = [new Criterion('content', 2, 1, 5), new Criterion('clarity', 1, 0, 10)];

        self::assertSame(1.0, Scoring::review($criteria, [new Score('content', 5), new Score('clarity', 10)]));
        self::assertSame(0.0, Scoring::review($criteria, [new Score('content', 1), new Score('clarity', 0)]));
        self::assertEqualsWithDelta((2 * 0.75 + 0.2) / 3, Scoring::review($criteria, [new Score('clarity', 2), new Score('content', 4)]), 1e-12);
        self::assertSame(0.75, Scoring::review($criteria, [new Score('content', 4)]));
        self::assertSame(0.0, Scoring::review($criteria, []));
    }

    public function testScoresAggregateByMeanOrMedian(): void
    {
        self::assertNull(Scoring::aggregate([], Aggregation::Mean));
        self::assertNull(Scoring::aggregate([], Aggregation::Median));
        self::assertSame(0.5, Scoring::aggregate([0.2, 0.9, 0.4], Aggregation::Mean));
        self::assertSame(0.4, Scoring::aggregate([0.2, 0.9, 0.4], Aggregation::Median));
        self::assertEqualsWithDelta(0.6, Scoring::aggregate([0.9, 0.2, 0.4, 0.8], Aggregation::Median), 1e-12);
        self::assertSame(0.7, Scoring::aggregate([0.7], Aggregation::Median));
        self::assertSame(0.7, Scoring::aggregate([0.7], Aggregation::Mean));
    }

    public function testACompleteReviewPassesTheCheck(): void
    {
        Scoring::check([new Criterion('a', 1, 0, 5), new Criterion('b', 1, -1, 1)], [new Score('b', -1), new Score('a', 5)]);
        Scoring::check([new Criterion('a', 1, 0, 5)], [new Score('a', 0)]);

        $this->expectNotToPerformAssertions();
    }

    public function testIncompleteOrOutOfScaleReviewsAreRefused(): void
    {
        $criteria = [new Criterion('a', 1, 0, 5), new Criterion('b', 1, 1, 3)];

        foreach ([
            [[new Score('a', 1)], 'The review does not score criterion "b".'],
            [[new Score('a', 1), new Score('a', 2), new Score('b', 1)], 'A review scores each criterion once.'],
            [[new Score('a', 6), new Score('b', 1)], 'The score of criterion "a" is 0 to 5.'],
            [[new Score('a', -1), new Score('b', 1)], 'The score of criterion "a" is 0 to 5.'],
            [[new Score('a', 1), new Score('b', 0)], 'The score of criterion "b" is 1 to 3.'],
            [[new Score('a', 1), new Score('b', 1), new Score('c', 1)], 'The review scores a criterion the campaign does not have.'],
        ] as [$scores, $message]) {
            try {
                Scoring::check($criteria, $scores);
                self::fail($message);
            } catch (InvalidInput $invalidInput) {
                self::assertSame($message, $invalidInput->getMessage());
            }
        }
    }
}
