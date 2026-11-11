<?php

declare(strict_types=1);

namespace Phil\Poll\Contract;

use Phil\Poll\Ballot;
use Phil\Poll\BallotKind;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\Poll\Result;

/**
 * A way of counting the ballots of a poll into standings. The library ships four; an application adds its own by
 * implementing this interface under a name of its own.
 */
interface CountingMethod
{
    /**
     * The name the method is chosen by, stored with a poll: lowercase ASCII letters, digits and `_`.
     */
    public function name(): string;

    /**
     * Whether the method counts the ballots of this kind.
     */
    public function supports(BallotKind $kind): bool;

    /**
     * Counts the ballots, read once, in their order. The method keeps no state between two counts.
     *
     * @param iterable<Ballot> $ballots
     *
     * @throws InvalidPoll   when the method does not count the rules' kind of ballot
     * @throws InvalidBallot when a ballot names a choice that is not one of the rules' options
     */
    public function count(PollRules $rules, iterable $ballots): Result;
}
