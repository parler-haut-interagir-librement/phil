<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * Where to find what a load line takes: the containers holding the item, its home first, and how many are there in all.
 */
final readonly class PickLine
{
    /**
     * @param list<Holding> $sources
     */
    public function __construct(
        public LoadLine $line,
        public array $sources,
        public int $available,
    ) {
    }

    public function isShort(): bool
    {
        return $this->available < $this->line->quantity;
    }
}
