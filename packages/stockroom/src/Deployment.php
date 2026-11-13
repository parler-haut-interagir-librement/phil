<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * What a deployment did: the movements it recorded, and the items it was short of. A deployment that refuses to go
 * short records no movement and lists every shortfall.
 */
final readonly class Deployment
{
    /**
     * @param list<Movement>  $movements
     * @param list<Shortfall> $shortfalls
     */
    public function __construct(
        public array $movements,
        public array $shortfalls,
    ) {
    }

    public function isComplete(): bool
    {
        return [] === $this->shortfalls;
    }
}
