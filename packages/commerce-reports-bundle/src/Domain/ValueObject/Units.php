<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;

/**
 * The bounds of a count of units: one line, one stock figure. A billion units is more than any shelf holds, and keeps
 * every sum and every product of the formulas inside the native integer.
 *
 * @internal
 */
final readonly class Units
{
    public const int MAX = 1_000_000_000;

    /**
     * @throws InvalidFigure when the quantity is below one or above a billion
     */
    public static function sold(int $quantity): int
    {
        if ($quantity < 1 || $quantity > self::MAX) {
            throw InvalidFigure::because(\sprintf('a quantity is from 1 to %d units', self::MAX));
        }

        return $quantity;
    }

    /**
     * @throws InvalidFigure when the count is below minus a billion or above a billion
     */
    public static function counted(string $what, int $count): int
    {
        if ($count < -self::MAX || $count > self::MAX) {
            throw InvalidFigure::because(\sprintf('a %s is from %d to %d units', $what, -self::MAX, self::MAX));
        }

        return $count;
    }
}
