<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * The ballots one option holds in a round of an instant runoff.
 */
final readonly class Tally
{
    public function __construct(
        public string $option,
        public int $votes,
    ) {
    }
}
