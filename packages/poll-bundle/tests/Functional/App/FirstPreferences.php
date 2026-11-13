<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional\App;

use Override;
use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\PollRules;
use Phil\Poll\Result;

/**
 * A counting method of the application: the first choice of each ranked ballot, counted as a single one.
 */
final readonly class FirstPreferences implements CountingMethod
{
    #[Override]
    public function name(): string
    {
        return 'first_preferences';
    }

    #[Override]
    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Ranked === $kind;
    }

    #[Override]
    public function count(PollRules $rules, iterable $ballots): Result
    {
        $firsts = [];

        foreach ($ballots as $ballot) {
            $firsts[] = Ballot::of(\array_slice($ballot->choices, 0, 1));
        }

        $result = new ChoiceCount()->count(PollRules::single($rules->options, true), $firsts);

        return new Result($this->name(), $result->ballots, $result->abstentions, $result->standings);
    }
}
