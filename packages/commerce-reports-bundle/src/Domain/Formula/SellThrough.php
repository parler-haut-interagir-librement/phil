<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\Formula;

use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;

/**
 * The sell-through of a period: the share of the units the shop could sell that it did sell, in parts per million.
 *
 *     sell-through = sold × 1 000 000 / (start + received)
 *
 * rounded to the nearest whole part, a half rounded up. With nothing to sell from — no starting stock and nothing
 * received, or a negative sum — there is no sell-through: null, never a division by zero. A period that sold more than
 * its stock, from backorders, gives more than a million.
 */
final class SellThrough
{
    public const int WHOLE = 1_000_000;

    /** The largest count either side of the division takes: the products of the formula stay inside the native integer. */
    public const int MAX_UNITS = 4_000_000_000_000;

    /**
     * @throws InvalidFigure when the units sold are negative, or a count is beyond four thousand billion
     */
    public static function ppm(int $sold, int $start, int $received): ?int
    {
        if ($sold < 0) {
            throw InvalidFigure::because('the units sold are not negative');
        }

        $available = $start + $received;

        if ($sold > self::MAX_UNITS || $available > self::MAX_UNITS) {
            throw InvalidFigure::because(\sprintf('a sell-through counts at most %d units', self::MAX_UNITS));
        }

        if ($available < 1) {
            return null;
        }

        // round(sold × 10⁶ / available), a half up, in integers: floor((2 × sold × 10⁶ + available) / (2 × available)).
        return intdiv(2 * $sold * self::WHOLE + $available, 2 * $available);
    }
}
