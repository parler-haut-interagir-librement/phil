<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\PollRules;

/**
 * Ranked ballots read once and grouped by identical ranking, so that a count that goes over them several times, or
 * pairs every two options of each, does so once per distinct ranking with its weight. Memory grows with the distinct
 * rankings, not with the ballots.
 *
 * @internal
 */
final readonly class BallotGroups
{
    /**
     * @param list<list<int>> $rankings the distinct rankings, as option indexes
     * @param list<int>       $weights  the ballots of each ranking
     */
    private function __construct(
        public array $rankings,
        public array $weights,
        public int $ballots,
        public int $abstentions,
    ) {
    }

    /**
     * @param iterable<Ballot> $ballots
     *
     * @throws InvalidBallot
     */
    public static function read(PollRules $rules, iterable $ballots): self
    {
        $index = Choices::index($rules);
        $rankings = [];
        $weights = [];
        $counted = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$counted;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            $ranking = Choices::indexes($ballot, $index);
            $key = implode(',', $ranking);
            $weights[$key] = ($weights[$key] ?? 0) + 1;
            $rankings[$key] = $ranking;
        }

        return new self(array_values($rankings), array_values($weights), $counted, $abstentions);
    }
}
