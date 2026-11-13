<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * The bounds of a quantity moved, planned or counted.
 */
final class Quantity
{
    /** The most pieces one movement or one line moves. */
    public const int MAX = 1_000_000;

    private function __construct()
    {
    }

    /**
     * @throws InvalidStock when the quantity is below one or above the bound
     */
    public static function check(int $quantity): int
    {
        if ($quantity < 1 || $quantity > self::MAX) {
            throw InvalidStock::because(\sprintf('A quantity is from 1 to %d, not %d.', self::MAX, $quantity));
        }

        return $quantity;
    }
}
