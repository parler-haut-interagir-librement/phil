<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * The price that reaches a target: a margin rate, a markup, or a share of the price taken by the cost.
 *
 * Every price is rounded away from zero unless a `\RoundingMode` is given: on a positive cost, the price is never
 * below the one the target asks, so the margin or markup it gives is never below the target. On a negated cost — a
 * refund — the price is the negation of the sale's.
 */
final readonly class PriceCalculator
{
    /**
     * The price whose margin rate is the target: `price = cost / (1 − margin)`. A cost of 6.00 at a 40 % margin is
     * a price of 10.00.
     *
     * @throws InvalidRate                when the margin is 100 % or more
     * @throws Exception\AmountOutOfRange when the cost times one million leaves the native range
     */
    public function priceForMargin(Money $cost, Rate $margin, RoundingMode $mode = RoundingMode::AwayFromZero): Money
    {
        return $this->scaled($cost, Rate::ONE_MILLION, IntegerMath::costShare($margin), $mode);
    }

    /**
     * The price whose markup on the cost is the target: `price = cost × (1 + markup)`. A cost of 6.00 at a 50 %
     * markup is a price of 9.00; a markup of -100 % is a zero price.
     *
     * @throws InvalidRate when the markup is below -100 %
     */
    public function priceForMarkup(Money $cost, Rate $markup, RoundingMode $mode = RoundingMode::AwayFromZero): Money
    {
        if ($markup->partsPerMillion < -Rate::ONE_MILLION) {
            throw InvalidRate::markupBelowMinusOneHundredPercent($markup->percent());
        }

        return $this->scaled($cost, $markup->partsPerMillion + Rate::ONE_MILLION, Rate::ONE_MILLION, $mode);
    }

    /**
     * The price in which the cost takes a target share: `price = cost / share`. A cost of 3.00 at a 30 % share is a
     * price of 10.00 — a target margin of 70 %, said the way a kitchen says its food cost.
     *
     * @throws InvalidRate when the share is zero or less
     */
    public function priceForCostShare(Money $cost, Rate $share, RoundingMode $mode = RoundingMode::AwayFromZero): Money
    {
        if ($share->partsPerMillion <= 0) {
            throw InvalidRate::costShareNotPositive($share->percent());
        }

        return $this->scaled($cost, Rate::ONE_MILLION, $share->partsPerMillion, $mode);
    }

    private function scaled(Money $cost, int $numerator, int $denominator, RoundingMode $mode): Money
    {
        $units = IntegerMath::divide(IntegerMath::multiply($cost->minorUnits, $numerator), $denominator, $mode);

        return Money::of($units, $cost->currency);
    }
}
