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
 * Ranked ballots counted by points: with n options, the first choice of a ballot earns n − 1 points, the second
 * n − 2, and so on; an option a ballot does not rank earns nothing from it. The highest total comes first, ties share
 * their place.
 */
final readonly class Borda implements CountingMethod
{
    public const string NAME = 'borda';

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
            throw InvalidPoll::because('The Borda count counts ranked ballots only.');
        }

        $index = Choices::index($rules);
        $top = \count($rules->options) - 1;
        $scores = array_fill(0, \count($rules->options), 0);
        $counted = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$counted;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            foreach (Choices::indexes($ballot, $index) as $rank => $position) {
                $scores[$position] += $top - $rank;
            }
        }

        return new Result(self::NAME, $counted, $abstentions, Places::byScore($rules->options, $scores));
    }
}
