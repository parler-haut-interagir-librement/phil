<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Cost;

use Phil\CommerceLogisticsBundle\Domain\Exception\CostRefused;
use Phil\Money\Money;

/**
 * The integer arithmetic of costs: a part of a value at its unit rate, and a landed cost shared out over lines. No
 * float touches an amount; a product that would overflow an integer is refused by `Money`.
 */
final class CostShare
{
    /**
     * The value of `$part` units out of `$whole` worth `$value` in all, rounded half up: the cost of units found at an
     * average, a unit cost. `$value` is zero or more, `$part` zero or more, `$whole` one or more.
     */
    public static function of(Money $value, int $part, int $whole): Money
    {
        $product = $value->multiply($part)->minorUnits;
        $remainder = $product % $whole;

        return Money::of(intdiv($product, $whole) + ($remainder >= $whole - $remainder ? 1 : 0), $value->currency);
    }

    /**
     * Shares a landed cost — freight, duties, brokerage — out over the lines that bear it, in proportion to their
     * weights, through `Money::allocate()`: the shares add up to the cost to the minor unit, the largest remainders
     * taking the minor units left. Lines whose weights are all zero share it by their units instead.
     *
     * @param list<array{string, int, int}> $lines the SKU, its weight — the line's value in minor units — and its
     *                                             units, each line one unit or more
     *
     * @return list<array{string, Money}> the SKU and its share, in the order of the lines
     */
    public static function landed(Money $cost, array $lines, string $reference): array
    {
        if ($cost->isNegative()) {
            throw CostRefused::negative($reference, $cost);
        }

        if ([] === $lines) {
            throw CostRefused::noLineBears($reference);
        }

        $weights = array_column($lines, 1);

        if (0 === array_sum($weights)) {
            $weights = array_column($lines, 2);
        }

        $shares = [];

        foreach ($cost->allocate(...$weights) as $index => $share) {
            $shares[] = [$lines[$index][0], $share];
        }

        return $shares;
    }
}
