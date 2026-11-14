<?php

declare(strict_types=1);

namespace Phil\Poll\Counting;

use Override;
use Phil\Poll\BallotKind;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\NetPromoter;
use Phil\Poll\PollRules;
use Phil\Poll\Rating;
use Phil\Poll\Result;

/**
 * The Net Promoter Score of a scale from 0 to 10: the distribution of the marks as the count of a scale gives it, their
 * sum, and the promoters (9 and 10), passives (7 and 8) and detractors (0 to 6) the score is made of.
 */
final readonly class NetPromoterScore implements CountingMethod
{
    public const string NAME = 'nps';

    private const int PROMOTER = 9;

    private const int PASSIVE = 7;

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
        if ($rules->options !== PollRules::netPromoter()->options) {
            throw InvalidPoll::because('A Net Promoter Score counts the marks of a scale from 0 to 10.');
        }

        $marks = Marks::read($rules, $ballots, 'Net Promoter Score');
        $groups = [0, 0, 0];

        foreach ($marks->counts as $mark => $count) {
            $groups[match (true) {
                $mark >= self::PROMOTER => 0,
                $mark >= self::PASSIVE => 1,
                default => 2,
            }] += $count;
        }

        return new Result(
            self::NAME,
            $marks->ballots,
            $marks->abstentions,
            Places::byScore($rules->options, $marks->counts),
            rating: new Rating($marks->marked(), $marks->sum($rules)),
            netPromoter: new NetPromoter($groups[0], $groups[1], $groups[2]),
        );
    }
}
