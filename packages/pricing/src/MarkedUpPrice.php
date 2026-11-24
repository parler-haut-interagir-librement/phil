<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * A price marked up from a cost above zero, by a markup above -100 %: the rule a suggested retail price and a
 * wholesale price share.
 *
 * @internal
 */
final class MarkedUpPrice
{
    private function __construct()
    {
    }

    /**
     * `basis × (1 + markup)`, rounded by `$mode`.
     *
     * @throws InvalidPricing             when the basis is zero or negative
     * @throws InvalidRate                when the markup is -100 % or less
     * @throws Exception\AmountOutOfRange when the basis times the markup leaves the native range
     */
    public static function of(Money $basis, Rate $markup, RoundingMode $mode): Money
    {
        if ($basis->isZero()) {
            throw InvalidPricing::zeroCost();
        }

        if ($basis->isNegative()) {
            throw InvalidPricing::negativeCost($basis->toDecimal());
        }

        if ($markup->partsPerMillion <= -Rate::ONE_MILLION) {
            throw InvalidRate::markupGivesNoPrice($markup->percent());
        }

        return new PriceCalculator()->priceForMarkup($basis, $markup, $mode);
    }
}
