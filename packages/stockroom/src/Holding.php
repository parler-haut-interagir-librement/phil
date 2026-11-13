<?php

declare(strict_types=1);

namespace Phil\Stockroom;

/**
 * How many pieces of an item a place holds.
 */
final readonly class Holding
{
    public function __construct(
        public string $item,
        public Place $place,
        public int $quantity,
    ) {
    }
}
