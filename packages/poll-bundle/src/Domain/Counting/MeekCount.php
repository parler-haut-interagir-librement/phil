<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting;

use Phil\Poll\PollRules;
use Phil\Poll\Standing;
use Phil\PollBundle\Domain\Status\CandidateStatus;
use Phil\PollBundle\Domain\Status\TieBreak;

/**
 * One count by Meek's method, round after round, as `MeekStv` describes it: the keep factor and the status of every
 * option, the options elected in their order, the excluded ones with the votes they held, and the votes of every
 * round before, for the tie-breaks. It lives for one count only.
 *
 * @internal
 */
final class MeekCount
{
    /** @var array<int, CandidateStatus> */
    private array $status;

    /** @var array<int, int> */
    private array $keep;

    /** @var list<int> */
    private array $elected = [];

    /** @var list<array{int, int}> */
    private array $excluded = [];

    /** @var list<array<int, int>> */
    private array $history = [];

    /** @var list<ElectionRound> */
    private array $rounds = [];

    /** @var array<int, int> the votes of the last pass */
    private array $votes = [];

    private int $exhausted = 0;

    private int $quota = 0;

    private int $surplus = 0;

    /**
     * @param list<list<int>> $rankings the distinct rankings, as option indexes
     * @param list<int>       $weights  the ballots of each ranking
     */
    public function __construct(
        private readonly PollRules $rules,
        private readonly array $rankings,
        private readonly array $weights,
        private readonly int $seats,
    ) {
        $this->status = array_fill(0, \count($rules->options), CandidateStatus::Hopeful);
        $this->keep = array_fill(0, \count($rules->options), Billionths::SCALE);
    }

    /**
     * Runs the next round: the passes until the keep factors settle, then the exclusion, the defeat of the options
     * left once the seats are filled, or the election of those that fit the seats left.
     */
    public function round(): ElectionRound
    {
        [$passes, $electedNow] = $this->settle();
        $tallied = $this->status;
        $hopeful = array_keys($this->status, CandidateStatus::Hopeful, true);
        $tieBreak = null;
        $out = [];

        if (\count($this->elected) >= $this->seats) {
            foreach ($hopeful as $candidate) {
                $this->status[$candidate] = CandidateStatus::Defeated;
                $out[] = $candidate;
            }
        } elseif (\count($this->elected) + \count($hopeful) <= $this->seats) {
            foreach ($this->byVotes($hopeful) as $candidate) {
                $this->elect($candidate);
                $electedNow[] = $candidate;
            }
        } else {
            $this->history[] = $this->votes;
            [$lowest, $tieBreak] = $this->lowest($hopeful);
            $this->status[$lowest] = CandidateStatus::Excluded;
            $this->keep[$lowest] = 0;
            $this->excluded[] = [$lowest, $this->votes[$lowest]];
            $out[] = $lowest;
        }

        $round = $this->record(\count($this->rounds) + 1, $passes, $tallied, $electedNow, $out, $tieBreak);
        $this->rounds[] = $round;

        return $round;
    }

    /**
     * @return list<string>
     */
    public function electedOptions(): array
    {
        return array_map($this->name(...), $this->elected);
    }

    /**
     * @return list<ElectionRound>
     */
    public function rounds(): array
    {
        return $this->rounds;
    }

    /**
     * The elected options first, sharing the first place, then the options defeated once the seats were filled by
     * their votes, then the excluded ones, the last excluded first.
     *
     * @return list<Standing>
     */
    public function standings(): array
    {
        $standings = [];

        foreach ($this->elected as $candidate) {
            $standings[] = new Standing($this->name($candidate), $this->votes[$candidate], 1);
        }

        foreach ($this->byVotes(array_keys($this->status, CandidateStatus::Defeated, true)) as $candidate) {
            $standings[] = new Standing($this->name($candidate), $this->votes[$candidate], \count($standings) + 1);
        }

        foreach (array_reverse($this->excluded) as [$candidate, $held]) {
            $standings[] = new Standing($this->name($candidate), $held, \count($standings) + 1);
        }

        return $standings;
    }

    /**
     * Passes over the ballots until the keep factors settle or the seats are filled, electing the options that reach
     * the quota on the way.
     *
     * @return array{int, list<int>} the passes run and the options elected
     */
    private function settle(): array
    {
        $passes = 0;
        $previous = null;
        $electedNow = [];

        while (true) {
            ++$passes;
            $this->pass();
            $newly = $this->reaching();

            foreach ($newly as $candidate) {
                $this->elect($candidate);
                $electedNow[] = $candidate;
            }

            $this->surplus = $this->surplus();

            if (\count($this->elected) >= $this->seats || ([] === $newly && $this->settled($passes, $previous))) {
                return [$passes, $electedNow];
            }

            $this->lowerKeepFactors();
            $previous = [] === $newly ? $this->surplus : null;
        }
    }

    /**
     * Whether the keep factors are taken as settled: the surplus under one hundred-thousandth of a vote, no smaller
     * than at the pass before, or the passes of a round all run.
     */
    private function settled(int $passes, ?int $previous): bool
    {
        return $this->surplus < MeekStv::SETTLED
            || $passes >= MeekStv::MAX_PASSES
            || (null !== $previous && $this->surplus >= $previous);
    }

    /**
     * One pass over the ballots: the billionths of a vote each option keeps, those no option kept, and the quota.
     */
    private function pass(): void
    {
        $votes = array_fill(0, \count($this->status), 0);
        $exhausted = 0;

        foreach ($this->rankings as $group => $ranking) {
            $value = Billionths::SCALE;

            foreach ($ranking as $candidate) {
                $kept = intdiv($value * $this->keep[$candidate], Billionths::SCALE);
                $votes[$candidate] += $this->weights[$group] * $kept;
                $value -= $kept;

                if (0 === $value) {
                    break;
                }
            }

            $exhausted += $this->weights[$group] * $value;
        }

        $this->votes = $votes;
        $this->exhausted = $exhausted;
        $this->quota = intdiv(array_sum($votes), $this->seats + 1) + 1;
    }

    /**
     * The hopeful options at or above the quota, the most votes first, the one declared first on a tie.
     *
     * @return list<int>
     */
    private function reaching(): array
    {
        $reaching = [];

        foreach ($this->status as $candidate => $standing) {
            if (CandidateStatus::Hopeful === $standing && $this->votes[$candidate] >= $this->quota) {
                $reaching[] = $candidate;
            }
        }

        return $this->byVotes($reaching);
    }

    private function surplus(): int
    {
        $surplus = 0;

        foreach ($this->status as $candidate => $standing) {
            if (CandidateStatus::Elected === $standing && $this->votes[$candidate] > $this->quota) {
                $surplus += $this->votes[$candidate] - $this->quota;
            }
        }

        return $surplus;
    }

    /**
     * Brings the keep factor of each elected option to quota ÷ votes of its own, rounded up.
     */
    private function lowerKeepFactors(): void
    {
        foreach ($this->status as $candidate => $standing) {
            if (CandidateStatus::Elected === $standing) {
                $this->keep[$candidate] = min(Billionths::SCALE, MeekStv::mulDivUp($this->keep[$candidate], $this->quota, $this->votes[$candidate]));
            }
        }
    }

    private function elect(int $candidate): void
    {
        $this->status[$candidate] = CandidateStatus::Elected;
        $this->elected[] = $candidate;
    }

    /**
     * @param list<int> $candidates
     *
     * @return list<int>
     */
    private function byVotes(array $candidates): array
    {
        $votes = $this->votes;
        usort($candidates, static fn (int $a, int $b): int => [$votes[$b], $a] <=> [$votes[$a], $b]);

        return $candidates;
    }

    /**
     * The hopeful option to exclude, and how a tie was broken: of those holding the fewest votes, the one whose votes
     * in the rounds before, read from the latest back, come first in ascending order; the one declared last when they
     * are equal.
     *
     * @param list<int> $hopeful more than the seats left, so never empty
     *
     * @return array{int, ?TieBreak}
     */
    private function lowest(array $hopeful): array
    {
        $tied = [];

        foreach ($hopeful as $candidate) {
            if ([] === $tied || $this->votes[$candidate] < $this->votes[$tied[0]]) {
                $tied = [$candidate];
            } elseif ($this->votes[$candidate] === $this->votes[$tied[0]]) {
                $tied[] = $candidate;
            }
        }

        $lowest = array_shift($tied) ?? 0;

        if ([] === $tied) {
            return [$lowest, null];
        }

        $history = array_reverse($this->history);
        $before = array_column($history, $lowest);
        $alike = 1;

        foreach ($tied as $candidate) {
            $past = array_column($history, $candidate);

            if ($past === $before) {
                $lowest = $candidate;
                ++$alike;
            } elseif ($past < $before) {
                $lowest = $candidate;
                $before = $past;
                $alike = 1;
            }
        }

        return [$lowest, 1 === $alike ? TieBreak::EarlierRounds : TieBreak::DeclaredOrder];
    }

    /**
     * @param array<int, CandidateStatus> $tallied the statuses before the end of the round, to list the options counted
     * @param list<int>                   $elected
     * @param list<int>                   $out
     */
    private function record(int $number, int $passes, array $tallied, array $elected, array $out, ?TieBreak $tieBreak): ElectionRound
    {
        $tallies = [];

        foreach ($tallied as $candidate => $standing) {
            if (CandidateStatus::Excluded !== $standing) {
                $tallies[] = new CandidateTally(
                    $this->name($candidate),
                    Billionths::of($this->votes[$candidate]),
                    Billionths::of(CandidateStatus::Excluded === $this->status[$candidate] ? Billionths::SCALE : $this->keep[$candidate]),
                    $this->status[$candidate],
                );
            }
        }

        return new ElectionRound(
            $number,
            $passes,
            Billionths::of($this->quota),
            $tallies,
            Billionths::of($this->exhausted),
            Billionths::of($this->surplus),
            array_map($this->name(...), $elected),
            array_map($this->name(...), $out),
            $tieBreak,
        );
    }

    private function name(int $candidate): string
    {
        return $this->rules->options[$candidate];
    }
}
