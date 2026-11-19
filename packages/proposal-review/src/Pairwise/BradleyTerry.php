<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Pairwise;

use Phil\ProposalReview\Exception\InvalidInput;

/**
 * The Bradley–Terry model fitted to a log of pairwise votes. Each proposal i gets a strength p(i) such that it is
 * preferred to j with probability p(i) / (p(i) + p(j)); the strengths that make the votes most likely are found by the
 * minorization–maximization iteration.
 *
 *     p(i) ← (W(i) + a) / ( Σ_j n(i, j) / (p(i) + p(j)) + 2a / (p(i) + 1) )
 *
 * where W(i) counts the wins of i, n(i, j) the votes between i and j, and a the prior: a wins and a losses against a
 * virtual reference proposal of strength 1, a log strength of 0. The prior keeps a proposal that never lost, or never
 * won, at a finite strength, pulls the proposals with few votes toward the reference, and fixes the scale, which the
 * votes alone leave free. Since the votes do not see the scale, each step ends by multiplying every strength by the
 * factor the prior prefers, found by bisection: without it, the iteration would crawl along the scale for thousands of
 * steps.
 *
 * All strengths are updated together from the previous step, so the order of the votes and of the proposals does not
 * change the result: the same log gives the same scores. The iteration stops when no log strength moves by more than
 * the tolerance, or after the most iterations allowed; each iteration costs O(P + D) for P proposals and D distinct
 * pairs voted, plus 60 steps of O(P) for the scale.
 */
final readonly class BradleyTerry
{
    public const int MAX_ITERATIONS = 10_000;

    /**
     * @throws InvalidInput
     */
    public function __construct(
        public float $prior = 1.0,
        public int $iterations = 500,
        public float $tolerance = 1e-9,
    ) {
        if ($prior <= 0.0 || $prior > 1_000.0) {
            throw InvalidInput::because('The prior of the Bradley–Terry model is above 0 and at most 1000.');
        }

        if ($iterations < 1 || $iterations > self::MAX_ITERATIONS) {
            throw InvalidInput::because(\sprintf('The Bradley–Terry model iterates 1 to %d times.', self::MAX_ITERATIONS));
        }

        if ($tolerance <= 0.0) {
            throw InvalidInput::because('The tolerance of the Bradley–Terry model is above 0.');
        }
    }

    /**
     * The score of each proposal, in the order given; votes about other proposals are left out.
     *
     * @param list<string> $proposals
     * @param list<Vote>   $votes
     *
     * @return list<PairwiseScore>
     */
    public function fit(array $proposals, array $votes): array
    {
        $index = array_flip($proposals);
        $count = \count($proposals);
        $wins = array_fill(0, $count, 0);
        $losses = array_fill(0, $count, 0);
        $pairs = [];

        foreach ($votes as $vote) {
            $winner = $index[$vote->winner] ?? null;
            $loser = $index[$vote->loser] ?? null;

            if (null === $winner || null === $loser) {
                continue;
            }

            ++$wins[$winner];
            ++$losses[$loser];
            $first = min($winner, $loser);
            $second = max($winner, $loser);
            $pairs[$first][$second] = ($pairs[$first][$second] ?? 0) + 1;
        }

        $edges = [];

        foreach ($pairs as $first => $row) {
            foreach ($row as $second => $games) {
                $edges[] = [$first, $second, $games];
            }
        }

        $strength = $this->iterate($count, $wins, $edges);
        $scores = [];

        foreach ($proposals as $position => $proposal) {
            $scores[] = new PairwiseScore($proposal, log($strength[$position]), $wins[$position], $losses[$position]);
        }

        return $scores;
    }

    /**
     * @param array<int, int>            $wins
     * @param list<array{int, int, int}> $edges
     *
     * @return array<int, float>
     */
    private function iterate(int $count, array $wins, array $edges): array
    {
        $strength = array_fill(0, $count, 1.0);

        for ($step = 0; $step < $this->iterations; ++$step) {
            $denominator = [];

            for ($i = 0; $i < $count; ++$i) {
                $denominator[$i] = 2 * $this->prior / ($strength[$i] + 1);
            }

            foreach ($edges as [$i, $j, $games]) {
                $share = $games / ($strength[$i] + $strength[$j]);
                $denominator[$i] += $share;
                $denominator[$j] += $share;
            }

            $next = [];

            for ($i = 0; $i < $count; ++$i) {
                $next[$i] = ($wins[$i] + $this->prior) / $denominator[$i];
            }

            $scale = $this->scale($next);
            $moved = 0.0;

            for ($i = 0; $i < $count; ++$i) {
                $next[$i] *= $scale;
                $moved = max($moved, abs(log($next[$i]) - log($strength[$i])));
            }

            $strength = $next;

            if ($moved < $this->tolerance) {
                break;
            }
        }

        return $strength;
    }

    /**
     * The common factor c that makes the strengths most likely, the votes being blind to it: the root of
     * Σ (1 − c·p(i)) / (1 + c·p(i)), found by bisection on log c between the bounds where every term has the same sign.
     *
     * @param array<int, float> $strength
     */
    private function scale(array $strength): float
    {
        if ([] === $strength) {
            return 1.0;
        }

        $logs = array_map(log(...), $strength);
        $low = -max($logs);
        $high = -min($logs);

        for ($step = 0; $step < 60 && $high - $low > 1e-15; ++$step) {
            $middle = ($low + $high) / 2;
            $factor = exp($middle);
            $balance = 0.0;

            foreach ($strength as $value) {
                $balance += (1 - $factor * $value) / (1 + $factor * $value);
            }

            if ($balance > 0) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return exp(($low + $high) / 2);
    }
}
