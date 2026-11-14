<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\PollRules;
use Phil\Poll\Rating;
use Phil\Poll\Result;

/**
 * The marks of a scale: each mark's score is the number of ballots that gave it — the distribution —, the mark given
 * most often first, and the result carries the sum of the marks, whose mean is the rating. Ties share their place.
 */
final readonly class ScaleCount implements CountingMethod
{
    public const string NAME = 'scale';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function supports(BallotKind $kind): bool
    {
        return BallotKind::Scale === $kind;
    }

    #[Override]
    public function count(PollRules $rules, iterable $ballots): Result
    {
        $marks = Marks::read($rules, $ballots, 'count of a scale');

        return new Result(self::NAME, $marks->ballots, $marks->abstentions, Places::byScore($rules->options, $marks->counts), rating: new Rating($marks->marked(), $marks->sum($rules)));
    }
}
