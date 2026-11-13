<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Counting\Contract;

use Phil\Poll\Ballot;
use Phil\Poll\Contract\CountingMethod;
use Phil\Poll\Exception\InvalidBallot;
use Phil\Poll\Exception\InvalidPoll;
use Phil\Poll\PollRules;
use Phil\PollBundle\Domain\Counting\Election;

/**
 * A counting method that fills several seats: a poll counted by it names how many, and its count is an election
 * with its published rounds. `count()` fills one seat.
 */
interface ElectingMethod extends CountingMethod
{
    /**
     * Counts the ballots, read once, into the options elected to the seats.
     *
     * @param iterable<Ballot> $ballots
     *
     * @throws InvalidPoll   when the method does not count the rules' kind of ballot, or the seats are not 1 to one
     *                       fewer than the options
     * @throws InvalidBallot when a ballot names a choice that is not one of the rules' options, or names one twice
     */
    public function elect(PollRules $rules, iterable $ballots, int $seats): Election;
}
