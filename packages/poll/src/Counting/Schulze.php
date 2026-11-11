<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;

/**
 * Ranked ballots counted by the Schulze method, a Condorcet method: every two options are compared on every ballot —
 * a ballot prefers an option it ranks to one it ranks lower or not at all, and prefers neither of two options it does
 * not rank —, the strength of a path between two options is its weakest pairwise win, counted in winning votes, and
 * an option beats another when its strongest path to it is stronger than the path back.
 *
 * An option's place counts the options that beat it: the options no other beats share the first place, and an
 * option that beats every other one wins alone. Its score is the number of options it beats.
 */
final readonly class Schulze implements CountingMethod
{
    public const string NAME = 'schulze';

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
            throw InvalidPoll::because('The Schulze method counts ranked ballots only.');
        }

        $groups = BallotGroups::read($rules, $ballots);
        $options = \count($rules->options);
        $zeros = array_fill(0, $options, 0);
        $paths = $this->strongestPaths($this->preferences($groups, $zeros), $zeros);
        $beats = $zeros;
        $beatenBy = $zeros;

        for ($a = 0; $a < $options; ++$a) {
            for ($b = 0; $b < $options; ++$b) {
                if ($paths[$a][$b] > $paths[$b][$a]) {
                    ++$beats[$a];
                    ++$beatenBy[$b];
                }
            }
        }

        return new Result(self::NAME, $groups->ballots, $groups->abstentions, Places::of($rules->options, $beats, $beatenBy));
    }

    /**
     * The ballots preferring each option to each other one.
     *
     * @param list<int> $zeros a zero per option
     *
     * @return array<int, array<int, int>>
     */
    private function preferences(BallotGroups $groups, array $zeros): array
    {
        $options = \count($zeros);
        $preferences = array_fill(0, $options, $zeros);

        foreach ($groups->rankings as $group => $ranking) {
            $weight = $groups->weights[$group];
            $below = array_fill(0, $options, true);

            foreach ($ranking as $preferred) {
                unset($below[$preferred]);

                foreach (array_keys($below) as $other) {
                    $preferences[$preferred][$other] += $weight;
                }
            }
        }

        return $preferences;
    }

    /**
     * The strength of the strongest path from each option to each other one, by the widest-path variant of
     * Floyd–Warshall over the pairwise wins. The diagonal is never read: going through an option's path to itself
     * changes no path to another option.
     *
     * @param array<int, array<int, int>> $preferences
     * @param list<int>                   $zeros       a zero per option
     *
     * @return array<int, array<int, int>>
     */
    private function strongestPaths(array $preferences, array $zeros): array
    {
        $options = \count($zeros);
        $paths = array_fill(0, $options, $zeros);

        for ($a = 0; $a < $options; ++$a) {
            for ($b = 0; $b < $options; ++$b) {
                if ($preferences[$a][$b] > $preferences[$b][$a]) {
                    $paths[$a][$b] = $preferences[$a][$b];
                }
            }
        }

        for ($via = 0; $via < $options; ++$via) {
            for ($a = 0; $a < $options; ++$a) {
                for ($b = 0; $b < $options; ++$b) {
                    $paths[$a][$b] = max($paths[$a][$b], min($paths[$a][$via], $paths[$via][$b]));
                }
            }
        }

        return $paths;
    }
}
