<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * One round of an instant runoff: the ballots each continuing option holds, in the options' declared order, the
 * ballots that rank no continuing option any more, and the options eliminated at the end of the round — none in the
 * last round.
 */
final readonly class Round
{
    /**
     * @param list<Tally>  $tallies
     * @param list<string> $eliminated
     */
    public function __construct(
        public array $tallies,
        public int $exhausted,
        public array $eliminated,
    ) {
    }
}
