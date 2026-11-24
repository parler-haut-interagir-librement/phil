<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Money;
use Phil\Pricing\Exception\UndefinedRate;
use RoundingMode;

/**
 * The outcome of a cascade: the original price, every step in the order the discounts were given, and the price
 * they leave.
 *
 * The steps chain exactly: each starts from the price the previous one left, and the discounts add up to the
 * original price less the final one, to the unit.
 */
final readonly class DiscountedPrice
{
    /**
     * @param list<DiscountStep> $steps
     */
    public function __construct(
        public Money $original,
        public array $steps,
        public Money $price,
    ) {
    }

    /**
     * The original price less the final one: the sum of the discounts.
     */
    public function totalDiscount(): Money
    {
        return $this->original->subtract($this->price);
    }

    /**
     * The total discount over the original price, in parts per million: the single rate the cascade amounts to on
     * this price. Discounts of 20 % then 10 % amount to 28 %, not 30 %.
     *
     * @throws UndefinedRate when the original price is zero
     */
    public function effectiveRate(RoundingMode $mode = RoundingMode::HalfAwayFromZero): Rate
    {
        if ($this->original->isZero()) {
            throw UndefinedRate::zeroOriginalPrice();
        }

        return IntegerMath::rate($this->totalDiscount()->minorUnits, $this->original->minorUnits, $mode);
    }
}
