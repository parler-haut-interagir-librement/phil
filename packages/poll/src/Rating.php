<?php

declare(strict_types=1);

namespace Phil\Poll;

/**
 * The marks of a poll on a scale: the ballots that gave one, abstentions left out, and their sum, both exact; the mean
 * is their quotient.
 */
final readonly class Rating
{
    public function __construct(
        public int $marked,
        public int $sum,
    ) {
    }

    /**
     * The mean mark, `null` while no ballot gave one.
     */
    public function mean(): ?float
    {
        return 0 === $this->marked ? null : $this->sum / $this->marked;
    }
}
