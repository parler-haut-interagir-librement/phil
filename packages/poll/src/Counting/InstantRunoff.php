<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\Poll\Round;
use Phil\Poll\Standing;
use Phil\Poll\Tally;

/**
 * Ranked ballots counted in rounds: each ballot goes to its highest-ranked option still in the count, and an option
 * holding more than half of the ballots still counting wins. Otherwise the option holding the fewest is eliminated
 * and its ballots go to their next choice; a ballot that ranks no option left is exhausted.
 *
 * A tie for the fewest ballots is broken by the rounds before, from the latest back: the option that held the fewest
 * in the latest round where the tied options differ is eliminated. When they never differed, the one declared last
 * is eliminated — an application that wants a lot instead declares the options of the poll in a random order. When
 * every option still in the count is tied, the count stops and they share the first place.
 *
 * The winner comes first, then the options still in the count by their ballots in the last round, then the
 * eliminated ones, the last eliminated first, each with the ballots it held when it was eliminated.
 */
final readonly class InstantRunoff implements CountingMethod
{
    public const string NAME = 'instant_runoff';

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
        if (!$this->supports($rules->kind)) {
            throw InvalidPoll::because('The instant runoff counts ranked ballots only.');
        }

        $groups = BallotGroups::read($rules, $ballots);
        $continuing = array_fill_keys(array_keys($rules->options), true);
        $tallies = [];
        $history = [];
        $rounds = [];
        $eliminated = [];

        while ([] !== $continuing) {
            [$tallies, $exhausted] = $this->round($groups, $continuing);
            $history[] = $tallies;
            $lowest = 2 * max($tallies) > array_sum($tallies) ? null : $this->lowest($tallies, $history);
            $rounds[] = $this->record($rules, $tallies, $exhausted, null === $lowest ? [] : [$lowest]);

            if (null === $lowest) {
                break;
            }

            $eliminated[] = [$lowest, $tallies[$lowest]];
            unset($continuing[$lowest]);
        }

        return new Result(self::NAME, $groups->ballots, $groups->abstentions, $this->standings($rules, $tallies, $eliminated), $rounds);
    }

    /**
     * The ballots each continuing option holds, and the exhausted ones.
     *
     * @param non-empty-array<int, true> $continuing
     *
     * @return array{non-empty-array<int, int>, int}
     */
    private function round(BallotGroups $groups, array $continuing): array
    {
        $tallies = array_fill_keys(array_keys($continuing), 0);
        $exhausted = 0;

        foreach ($groups->rankings as $group => $ranking) {
            foreach ($ranking as $position) {
                if (isset($continuing[$position])) {
                    $tallies[$position] += $groups->weights[$group];

                    continue 2;
                }
            }

            $exhausted += $groups->weights[$group];
        }

        return [$tallies, $exhausted];
    }

    /**
     * The option to eliminate after the latest round, `null` when every continuing option is tied: of the options
     * holding the fewest ballots, the one whose ballots in the rounds before, read from the latest back, come first in
     * ascending order, the one declared last when they are equal.
     *
     * @param non-empty-array<int, int> $latest
     * @param list<array<int, int>>     $history the rounds so far, the latest included
     */
    private function lowest(array $latest, array $history): ?int
    {
        $tied = array_keys($latest, min($latest), true);

        if (\count($tied) === \count($latest)) {
            return null;
        }

        $history = array_reverse($history);
        $lowest = null;
        $fewest = null;

        foreach ($tied as $position) {
            $ballots = array_column($history, $position);

            if (null === $fewest || $ballots <= $fewest) {
                $lowest = $position;
                $fewest = $ballots;
            }
        }

        return $lowest;
    }

    /**
     * @param array<int, int> $tallies
     * @param list<int>       $eliminated
     */
    private function record(PollRules $rules, array $tallies, int $exhausted, array $eliminated): Round
    {
        $recorded = [];

        foreach ($tallies as $position => $votes) {
            $recorded[] = new Tally($rules->options[$position], $votes);
        }

        return new Round($recorded, $exhausted, array_map(static fn (int $position): string => $rules->options[$position], $eliminated));
    }

    /**
     * @param array<int, int>       $tallies    the last round
     * @param list<array{int, int}> $eliminated each eliminated option and the ballots it held, in the order of elimination
     *
     * @return list<Standing>
     */
    private function standings(PollRules $rules, array $tallies, array $eliminated): array
    {
        $standings = Places::byScore(
            array_map(static fn (int $position): string => $rules->options[$position], array_keys($tallies)),
            array_values($tallies),
        );

        foreach (array_reverse($eliminated) as [$position, $votes]) {
            $standings[] = new Standing($rules->options[$position], $votes, \count($standings) + 1);
        }

        return $standings;
    }
}
