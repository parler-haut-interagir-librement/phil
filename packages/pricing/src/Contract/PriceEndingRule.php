<?php

declare(strict_types=1);

namespace Phil\Pricing\Contract;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;

/**
 * How a computed price is ended for the shelf: 19.99 rather than 19.37.
 *
 * `PriceEnding` and `PriceEndingBands` implement it; an application may implement it for a rule of its own, a table
 * of hand-picked prices for instance.
 */
interface PriceEndingRule
{
    /**
     * The ended price: never negative, and never below `$floor` when one is given.
     *
     * @throws InvalidPricing   when the price is negative
     * @throws CurrencyMismatch when the price, the floor and the rule differ in currency
     */
    public function apply(Money $price, ?Money $floor = null): Money;
}
