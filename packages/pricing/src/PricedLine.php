<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;

/**
 * A unit price, a unit cost and a quantity: one line of a sale, a report or a category.
 *
 * The price is what the customer pays per unit before taxes, after discounts. A refund negates the price and the
 * cost, or the quantity; a price and a cost of opposite signs are refused. Either may be zero: a free item has a
 * zero price, a donated item a zero cost.
 */
final readonly class PricedLine
{
    private function __construct(
        public Money $price,
        public Money $cost,
        public int $quantity,
        public Money $revenue,
        public Money $totalCost,
    ) {
    }

    /**
     * @throws InvalidPricing   when the price and the cost have opposite signs
     * @throws CurrencyMismatch when they are in different currencies
     * @throws InvalidAmount    when a total leaves the native range
     */
    public static function of(Money $price, Money $cost, int $quantity = 1): self
    {
        if (($price->isPositive() && $cost->isNegative()) || ($price->isNegative() && $cost->isPositive())) {
            throw InvalidPricing::oppositeSigns($price->toDecimal(), $cost->toDecimal());
        }

        $price->compare($cost);

        return new self($price, $cost, $quantity, $price->multiply($quantity), $cost->multiply($quantity));
    }

    /**
     * The revenue less the total cost.
     */
    public function margin(): Money
    {
        return $this->revenue->subtract($this->totalCost);
    }
}
