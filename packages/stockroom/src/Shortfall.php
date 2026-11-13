<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * An item of which fewer pieces are there than expected: short of stock for a deployment, or missing at the return.
 */
final readonly class Shortfall
{
    public function __construct(
        public string $item,
        public int $expected,
        public int $found,
    ) {
    }

    /**
     * The pieces lacking.
     */
    public function missing(): int
    {
        return $this->expected - $this->found;
    }
}
