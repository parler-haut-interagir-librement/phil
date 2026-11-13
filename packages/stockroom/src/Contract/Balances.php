<?php

declare(strict_types=1);

namespace Phil\Stockroom\Contract;

use Phil\Stockroom\Holding;
use Phil\Stockroom\Place;

/**
 * How many pieces of each item each place holds. A quantity set to zero removes the line.
 */
interface Balances
{
    public function quantity(string $item, Place $place): int;

    public function set(string $item, Place $place, int $quantity): void;

    /**
     * The places holding pieces of an item, in the order of their keys.
     *
     * @return list<Holding>
     */
    public function of(string $item): array;

    /**
     * The items a place holds, at most so many, in the order of their identifiers.
     *
     * @return list<Holding>
     */
    public function at(Place $place, int $limit): array;
}
