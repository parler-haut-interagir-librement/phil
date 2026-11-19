<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Pairwise;

use DateTimeImmutable;
use Phil\ProposalReview\Exception\InvalidInput;
use Phil\ProposalReview\Pairwise\BradleyTerry;
use Phil\ProposalReview\Pairwise\PairwiseScore;
use Phil\ProposalReview\Pairwise\Vote;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(BradleyTerry::class)]
#[CoversClass(PairwiseScore::class)]
#[CoversClass(Vote::class)]
final class BradleyTerryTest extends TestCase
{
    public function testWithoutVotesEveryProposalIsAverage(): void
    {
        $scores = new BradleyTerry()->fit(['a', 'b'], []);

        self::assertEquals([new PairwiseScore('a', 0.0, 0, 0), new PairwiseScore('b', 0.0, 0, 0)], $scores);
        self::assertSame([], new BradleyTerry()->fit([], []));
    }

    public function testTheStrongerWinsMoreAndTheScoresSolveTheModel(): void
    {
        $votes = $this->votes([['a', 'b', 6], ['b', 'a', 2], ['b', 'c', 5], ['c', 'b', 3], ['a', 'c', 4], ['c', 'a', 1]]);
        $scores = new BradleyTerry(prior: 0.5)->fit(['a', 'b', 'c'], $votes);
        [$a, $b, $c] = $scores;

        self::assertGreaterThan($b->strength, $a->strength);
        self::assertGreaterThan($c->strength, $b->strength);
        self::assertSame([10, 3], [$a->wins, $a->losses]);
        self::assertSame(13, $a->comparisons());

        // At the fit, each proposal's wins plus the prior equal the wins the model expects, the prior's games included.
        $p = array_map(static fn (PairwiseScore $score): float => exp($score->strength), $scores);
        $expected = 8 * $p[0] / ($p[0] + $p[1]) + 5 * $p[0] / ($p[0] + $p[2]) + 2 * 0.5 * $p[0] / ($p[0] + 1);
        self::assertEqualsWithDelta(10 + 0.5, $expected, 1e-6);
    }

    public function testAnUnbeatenProposalStaysFinite(): void
    {
        [$a, $b] = new BradleyTerry()->fit(['a', 'b'], $this->votes([['a', 'b', 50]]));

        self::assertTrue(is_finite($a->strength));
        self::assertGreaterThan(0.0, $a->strength);
        self::assertEqualsWithDelta(-$a->strength, $b->strength, 1e-9);
        self::assertGreaterThan(0.9, $a->chanceAgainst($b));
        self::assertEqualsWithDelta(1.0, $a->chanceAgainst($b) + $b->chanceAgainst($a), 1e-12);
    }

    public function testTheResultDoesNotDependOnTheOrderOfTheLog(): void
    {
        $votes = $this->votes([['a', 'b', 3], ['b', 'c', 2], ['c', 'a', 1], ['a', 'c', 2], ['d', 'a', 1]]);
        $model = new BradleyTerry();
        $forward = $model->fit(['a', 'b', 'c', 'd'], $votes);
        $backward = $model->fit(['a', 'b', 'c', 'd'], array_reverse($votes));

        foreach ($forward as $position => $score) {
            self::assertEqualsWithDelta($score->strength, $backward[$position]->strength, 1e-12);
        }
    }

    public function testVotesAboutOtherProposalsAreLeftOut(): void
    {
        $scores = new BradleyTerry()->fit(['a', 'b'], $this->votes([['a', 'x', 5], ['y', 'b', 5], ['a', 'b', 1]]));

        self::assertSame([1, 0], [$scores[0]->wins, $scores[0]->losses]);
        self::assertSame([0, 1], [$scores[1]->wins, $scores[1]->losses]);
    }

    public function testFewIterationsStopEarly(): void
    {
        $votes = $this->votes([['a', 'b', 9], ['b', 'a', 1]]);
        $one = new BradleyTerry(iterations: 1)->fit(['a', 'b'], $votes);
        $many = new BradleyTerry()->fit(['a', 'b'], $votes);
        $loose = new BradleyTerry(tolerance: 1.0)->fit(['a', 'b'], $votes);

        self::assertNotEqualsWithDelta($one[0]->strength, $many[0]->strength, 1e-6);
        self::assertEqualsWithDelta($one[0]->strength, $loose[0]->strength, 1e-12);
        self::assertEqualsWithDelta($many[0]->strength, new BradleyTerry(iterations: BradleyTerry::MAX_ITERATIONS)->fit(['a', 'b'], $votes)[0]->strength, 1e-8);
    }

    public function testTheRatingScale(): void
    {
        self::assertSame(1500.0, new PairwiseScore('a', 0.0, 0, 0)->rating());
        self::assertEqualsWithDelta(1900.0, new PairwiseScore('a', log(10), 0, 0)->rating(), 1e-9);
        self::assertSame(0.5, new PairwiseScore('a', 0.3, 0, 0)->chanceAgainst(new PairwiseScore('b', 0.3, 0, 0)));
    }

    public function testInvalidSettingsAndVotesAreRefused(): void
    {
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');

        foreach ([
            [static fn (): BradleyTerry => new BradleyTerry(prior: 0.0), 'The prior of the Bradley–Terry model is above 0 and at most 1000.'],
            [static fn (): BradleyTerry => new BradleyTerry(prior: 1000.5), 'The prior of the Bradley–Terry model is above 0 and at most 1000.'],
            [static fn (): BradleyTerry => new BradleyTerry(iterations: 0), 'The Bradley–Terry model iterates 1 to 10000 times.'],
            [static fn (): BradleyTerry => new BradleyTerry(iterations: BradleyTerry::MAX_ITERATIONS + 1), 'The Bradley–Terry model iterates 1 to 10000 times.'],
            [static fn (): BradleyTerry => new BradleyTerry(tolerance: 0.0), 'The tolerance of the Bradley–Terry model is above 0.'],
            [static fn (): Vote => new Vote('c', 0, 'v', 'a', 'b', $at), 'The votes of a log are numbered from 1.'],
            [static fn (): Vote => new Vote('c', 1, 'v', 'a', 'a', $at), 'A vote compares two different proposals.'],
            [static fn (): Vote => new Vote('c', 1, 'v@x.example', 'a', 'b', $at), 'Voter identifiers are stable opaque identifiers, never e-mail addresses.'],
        ] as [$build, $message]) {
            try {
                $build();
                self::fail($message);
            } catch (InvalidInput $invalidInput) {
                self::assertSame($message, $invalidInput->getMessage());
            }
        }

        self::assertSame(1000.0, new BradleyTerry(prior: 1000.0)->prior);
        $vote = new Vote('c', 1, 'v', 'a', 'b', new DateTimeImmutable('2026-11-12 10:00:00 Asia/Tokyo'));
        self::assertSame(['c', 1, 'v', 'a', 'b', '2026-11-12T01:00:00+00:00'], [$vote->campaign, $vote->sequence, $vote->voter, $vote->winner, $vote->loser, $vote->castAt->format(\DATE_ATOM)]);
    }

    /**
     * @param list<array{string, string, int}> $results winner, loser, times
     *
     * @return list<Vote>
     */
    private function votes(array $results): array
    {
        $votes = [];
        $at = new DateTimeImmutable('2026-11-12 10:00:00 UTC');

        foreach ($results as [$winner, $loser, $times]) {
            for ($time = 0; $time < $times; ++$time) {
                $votes[] = new Vote('cfp', \count($votes) + 1, 'v', $winner, $loser, $at);
            }
        }

        return $votes;
    }

    public function testTheDefaultsAreAPriorOfOneFiveHundredIterationsAndANanoTolerance(): void
    {
        $model = new BradleyTerry();

        self::assertSame([1.0, 500, 1e-9], [$model->prior, $model->iterations, $model->tolerance]);
    }

    public function testTheIterationStopsWhenNoLogStrengthMovesByTheTolerance(): void
    {
        $votes = $this->votes([['a', 'b', 6], ['b', 'a', 2], ['b', 'c', 5], ['c', 'b', 3], ['a', 'c', 4], ['c', 'a', 1]]);
        $strengths = static fn (BradleyTerry $model): array => array_map(static fn (PairwiseScore $score): float => $score->strength, $model->fit(['a', 'b', 'c'], $votes));

        $loose = $strengths(new BradleyTerry(tolerance: 0.05));

        self::assertSame($strengths(new BradleyTerry(iterations: 4, tolerance: 1e-300)), $loose);
        self::assertNotSame($strengths(new BradleyTerry(iterations: 3, tolerance: 1e-300)), $loose);
        self::assertNotSame($strengths(new BradleyTerry(iterations: 5, tolerance: 1e-300)), $loose);
    }

    public function testTheScaleIsTheRootOfTheBalanceOfTheStrengths(): void
    {
        $scale = new ReflectionMethod(BradleyTerry::class, 'scale');
        $model = new BradleyTerry();

        self::assertSame(1.0, $scale->invoke($model, []));
        self::assertEqualsWithDelta(0.5, $scale->invoke($model, [2.0]), 1e-14);
        self::assertEqualsWithDelta(10 / 3, $scale->invoke($model, [0.3]), 1e-14);
        self::assertEqualsWithDelta(1.0, $scale->invoke($model, [5.0, 0.2]), 1e-14);
        self::assertEqualsWithDelta(0.88144639378728, $scale->invoke($model, [3.0, 1.0, 0.5]), 1e-14);
    }

    public function testAMoveEqualToTheToleranceDoesNotStopTheIteration(): void
    {
        $votes = $this->votes([['a', 'b', 6], ['b', 'a', 2], ['b', 'c', 5], ['c', 'b', 3], ['a', 'c', 4], ['c', 'a', 1]]);
        $first = new BradleyTerry(iterations: 1)->fit(['a', 'b', 'c'], $votes);
        $moved = max(0.0, ...array_map(static fn (PairwiseScore $score): float => abs($score->strength), $first));

        $exact = new BradleyTerry(tolerance: $moved)->fit(['a', 'b', 'c'], $votes);

        self::assertNotEquals($first, $exact);
    }
}
