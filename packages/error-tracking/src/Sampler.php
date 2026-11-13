<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use Phil\ErrorTracking\Exception\InvalidOption;
use Random\Randomizer;

/**
 * Keeps a share of the events, drawn at random: 1.0 keeps them all, 0.25 one in four, 0.0 none. A site that throws
 * the same error on every page keeps its groups at a fraction of the writes.
 */
final readonly class Sampler
{
    /**
     * @throws InvalidOption when the rate is outside 0.0 to 1.0
     */
    public function __construct(
        public float $rate = 1.0,
        private Randomizer $randomizer = new Randomizer(),
    ) {
        if ($rate < 0.0 || $rate > 1.0) {
            throw InvalidOption::because('A sample rate lies between 0.0 and 1.0.');
        }
    }

    public function keep(): bool
    {
        return $this->randomizer->nextFloat() < $this->rate;
    }
}
