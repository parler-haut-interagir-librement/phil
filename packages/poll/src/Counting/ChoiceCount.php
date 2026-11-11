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
 * Single and multiple ballots counted choice by choice: an option's score is the number of ballots that chose it, the
 * plurality of a single ballot and the approval count of a multiple one. Ties share their place.
 */
final readonly class ChoiceCount implements CountingMethod
{
    public const string NAME = 'choices';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Ranked !== $kind;
    }

    #[Override]
    public function count(PollRules $rules, iterable $ballots): Result
    {
        if (!$this->supports($rules->kind)) {
            throw InvalidPoll::because('The count of choices does not count ranked ballots.');
        }

        $index = Choices::index($rules);
        $scores = array_fill(0, \count($rules->options), 0);
        $counted = 0;
        $abstentions = 0;

        foreach ($ballots as $ballot) {
            ++$counted;

            if ($ballot->isAbstention()) {
                ++$abstentions;

                continue;
            }

            foreach (Choices::indexes($ballot, $index) as $position) {
                ++$scores[$position];
            }
        }

        return new Result(self::NAME, $counted, $abstentions, Places::byScore($rules->options, $scores));
    }
}
