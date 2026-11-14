<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;

/**
 * The ballots of a scale read once: how many gave each mark, by the position of its option, and how many abstained.
 *
 * @internal
 */
final readonly class Marks
{
    /**
     * @param array<int, int> $counts the ballots that gave each mark, by the position of its option
     */
    private function __construct(
        public array $counts,
        public int $ballots,
        public int $abstentions,
    ) {
    }

    /**
     * @param iterable<Ballot> $ballots
     *
     * @throws InvalidPoll   when the rules are not those of a scale
     * @throws InvalidBallot when a ballot holds something else than one mark of the scale
     */
    public static function read(PollRules $rules, iterable $ballots, string $method): self
    {
        if (BallotKind::Scale !== $rules->kind) {
            throw InvalidPoll::because(\sprintf('The %s counts the marks of a scale only.', $method));
        }

        $index = Choices::index($rules);
        $counts = array_fill(0, \count($rules->options), 0);
        $counted = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$counted;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            $positions = Choices::indexes($ballot, $index);

            if (1 !== \count($positions)) {
                throw InvalidBallot::because('A ballot on a scale holds exactly one mark.');
            }

            ++$counts[$positions[0]];
        }

        return new self($counts, $counted, $abstentions);
    }

    /**
     * The sum of the marks given.
     */
    public function sum(PollRules $rules): int
    {
        $sum = 0;

        foreach ($this->counts as $position => $count) {
            $sum += (int) $rules->options[$position] * $count;
        }

        return $sum;
    }

    public function marked(): int
    {
        return $this->ballots - $this->abstentions;
    }
}
