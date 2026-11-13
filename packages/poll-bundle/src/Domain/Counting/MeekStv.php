<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use InvalidArgumentException;
use Override;
use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Poll\Standing;
use Phil\PollBundle\Domain\Counting\Contract\ElectingMethod;

/**
 * The single transferable vote by Meek's method, on ranked ballots, in exact rational numbers of denominator one
 * billion.
 *
 * Every option has a keep factor: 1 while it is hopeful, 0 once excluded, and, once elected, the part of each vote
 * reaching it that it keeps, the rest passing to the ballot's next choice. A pass over the ballots gives each option
 * the votes it keeps; the value left at the end of a ballot is exhausted. The quota is the votes kept by the options
 * divided by one more than the seats, truncated, plus one billionth. A hopeful option at or above it is elected. The
 * keep factor of each elected option is then brought down to quota ÷ votes, and the pass starts again, until the
 * surplus of the elected options over the quota is under one hundred-thousandth of a vote, stops shrinking, or a
 * thousand passes have run. The hopeful option with the fewest votes is then excluded, and a new round begins. The
 * count ends when the seats are filled, the hopeful options left being defeated, or when the hopeful options left
 * are no more than the seats still empty, and are all elected.
 *
 * Roundings go one way each, so that no vote is ever made: the part an option keeps of a vote is truncated, the rest
 * passing on whole; a keep factor is rounded up. The votes kept and the value exhausted always add up to the ballots
 * counted.
 *
 * A tie for the fewest votes is broken by the rounds before, from the latest back: the option that held the fewest in
 * the latest round where the tied options differ is excluded. When they never differed, the one declared last is —
 * an application that wants a lot instead declares the options of the poll in a random order.
 *
 * A count reads the ballots once and groups identical rankings. It runs at most one round per option and a thousand
 * passes per round; a pass goes over the distinct rankings. It keeps no state between two counts.
 */
final readonly class MeekStv implements ElectingMethod
{
    public const string NAME = 'meek_stv';

    /** The surplus, in billionths, under which the keep factors are taken as settled: one hundred-thousandth of a vote. */
    public const int SETTLED = 10_000;

    /** The passes over the ballots a round runs at most. */
    public const int MAX_PASSES = 1_000;

    /** The ballots a count holds at most, so that the votes, in billionths, stay within an integer. */
    public const int MAX_BALLOTS = 1_000_000_000;

    /**
     * @param int $maxBallots the ballots a count holds at most, 1 to `MAX_BALLOTS`: an application may lower it
     *
     * @throws InvalidArgumentException when the bound is out of its range
     */
    public function __construct(
        private int $maxBallots = self::MAX_BALLOTS,
    ) {
        if ($maxBallots < 1 || $maxBallots > self::MAX_BALLOTS) {
            throw new InvalidArgumentException(\sprintf('A single transferable vote counts at most 1 to %d ballots, not %d.', self::MAX_BALLOTS, $maxBallots));
        }
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Ranked === $kind;
    }

    #[Override]
    public function count(PollRules $rules, iterable $ballots): Result
    {
        return $this->elect($rules, $ballots, 1)->toResult();
    }

    #[Override]
    public function elect(PollRules $rules, iterable $ballots, int $seats): Election
    {
        if (!$this->supports($rules->kind)) {
            throw InvalidPoll::because('The single transferable vote counts ranked ballots only.');
        }

        $candidates = \count($rules->options);

        if ($seats < 1 || $seats >= $candidates) {
            throw InvalidPoll::because(\sprintf('A single transferable vote fills 1 to %d seats among %d options, not %d.', $candidates - 1, $candidates, $seats));
        }

        [$rankings, $weights, $counted, $abstentions] = $this->read($rules, $ballots);

        if ([] === $rankings) {
            return new Election(self::NAME, $seats, $counted, $abstentions, [], [], array_map(static fn (string $option): Standing => new Standing($option, 0, 1), $rules->options));
        }

        $count = new MeekCount($rules, $rankings, $weights, $seats);

        do {
            $round = $count->round();
        } while (!$round->isLast());

        return new Election(self::NAME, $seats, $counted, $abstentions, $count->electedOptions(), $count->rounds(), $count->standings());
    }

    /**
     * ⌈a × b ÷ c⌉ for 0 ≤ a ≤ one billion and 0 ≤ b, 0 < c below 2⁶², without the product leaving an integer.
     */
    public static function mulDivUp(int $a, int $b, int $c): int
    {
        $whole = intdiv($b, $c);
        $rest = $b % $c;
        $quotient = 0;
        $remainder = 0;

        for ($bit = 29; $bit >= 0; --$bit) {
            $quotient *= 2;
            $remainder *= 2;

            if ($remainder >= $c) {
                ++$quotient;
                $remainder -= $c;
            }

            if (1 === (($a >> $bit) & 1)) {
                $remainder += $rest;

                if ($remainder >= $c) {
                    ++$quotient;
                    $remainder -= $c;
                }
            }
        }

        return $a * $whole + $quotient + ($remainder > 0 ? 1 : 0);
    }

    /**
     * The ballots read once, each choice checked, grouped by identical ranking.
     *
     * @param iterable<Ballot> $ballots
     *
     * @return array{list<list<int>>, list<int>, int, int}
     *
     * @throws InvalidBallot
     * @throws InvalidPoll
     */
    private function read(PollRules $rules, iterable $ballots): array
    {
        $index = array_flip($rules->options);
        $rankings = [];
        $weights = [];
        $counted = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            if (++$counted > $this->maxBallots) {
                throw InvalidPoll::because(\sprintf('A single transferable vote counts at most %d ballots.', $this->maxBallots));
            }

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            $ranking = [];

            foreach ($ballot->choices as $choice) {
                $position = $index[$choice] ?? throw InvalidBallot::because(\sprintf('"%s" is not an option of this poll.', $choice));

                if (\in_array($position, $ranking, true)) {
                    throw InvalidBallot::because(\sprintf('The option "%s" is chosen twice.', $choice));
                }

                $ranking[] = $position;
            }

            $key = implode(',', $ranking);
            $weights[$key] = ($weights[$key] ?? 0) + 1;
            $rankings[$key] = $ranking;
        }

        return [array_values($rankings), array_values($weights), $counted, $abstentions];
    }
}
