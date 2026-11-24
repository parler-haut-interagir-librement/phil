<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * Successive discounts: each applies to the price the previous one left, in the order given.
 *
 * Each step rounds its own discount to the unit, half away from zero unless a `\RoundingMode` is given, so a
 * negated price — a refund — gives the negated steps. The order is kept and every step is exposed: a receipt can
 * print each discount on its own line, and the cascade never reorders what the application decided.
 */
final readonly class DiscountCascade
{
    /**
     * @param list<Rate> $discounts each from 0 to 100 %
     *
     * @throws InvalidRate when a discount is outside 0 to 100 %
     */
    public function apply(Money $price, array $discounts, RoundingMode $mode = RoundingMode::HalfAwayFromZero): DiscountedPrice
    {
        $steps = [];
        $current = $price;

        foreach ($discounts as $rate) {
            if ($rate->partsPerMillion < 0 || $rate->partsPerMillion > Rate::ONE_MILLION) {
                throw InvalidRate::discountOutOfRange($rate->percent());
            }

            $discount = Money::of(
                IntegerMath::divide(IntegerMath::multiply($current->minorUnits, $rate->partsPerMillion), Rate::ONE_MILLION, $mode),
                $current->currency,
            );
            $after = $current->subtract($discount);
            $steps[] = new DiscountStep($rate, $current, $discount, $after);
            $current = $after;
        }

        return new DiscountedPrice($price, $steps, $current);
    }
}
