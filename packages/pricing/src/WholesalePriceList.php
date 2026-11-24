<?php

declare(strict_types=1);

namespace Phil\Pricing;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Money;
use Phil\Pricing\Exception\InvalidPricing;
use Phil\Pricing\Exception\InvalidRate;
use RoundingMode;

/**
 * A wholesale price from a cost and a markup, `cost × (1 + markup)`, with optional quantity tiers: from a number
 * of units, a lower unit price.
 *
 * The first tier, from one unit, is the cost marked up. Each tier added starts above the previous one and never
 * raises the unit price; tiers are added in ascending order of their quantities, never ranked by a number. The unit
 * price of a quantity is the one of the last tier it reaches. Prices from a markup round away from zero unless a
 * `\RoundingMode` is given: the markup is always reached.
 */
final readonly class WholesalePriceList
{
    /**
     * @param non-empty-list<WholesaleTier> $tiers
     */
    private function __construct(
        public Money $cost,
        public RoundingMode $mode,
        public array $tiers,
    ) {
    }

    /**
     * A cost of 6.00 at a 30 % markup is a wholesale price of 7.80 a unit.
     *
     * @throws InvalidPricing             when the cost is zero or negative, or the price rounds to zero
     * @throws InvalidRate                when the markup is -100 % or less
     * @throws Exception\AmountOutOfRange when the cost times the markup leaves the native range
     */
    public static function fromCost(Money $cost, Rate $markup, RoundingMode $mode = RoundingMode::AwayFromZero): self
    {
        $unitPrice = self::markedUp($cost, $markup, $mode);

        return new self($cost, $mode, [new WholesaleTier(1, $unitPrice, $markup)]);
    }

    /**
     * A new list with a tier from `$quantity` units at `$unitPrice`.
     *
     * @throws InvalidPricing   when the unit price is not above zero, the quantity does not start above the
     *                          previous tier, or the unit price is above the previous tier's
     * @throws CurrencyMismatch when the unit price is not in the cost's currency
     */
    public function from(int $quantity, Money $unitPrice): self
    {
        $unitPrice->compare($this->cost);

        if (!$unitPrice->isPositive()) {
            throw InvalidPricing::unitPriceNotPositive($unitPrice->toDecimal());
        }

        return $this->with(new WholesaleTier($quantity, $unitPrice, null));
    }

    /**
     * A new list with a tier from `$quantity` units, priced from the cost by `$markup`: a lower markup for a larger
     * quantity.
     *
     * @throws InvalidPricing when the quantity does not start above the previous tier, or the price is above the
     *                        previous tier's
     * @throws InvalidRate    when the markup is -100 % or less
     */
    public function fromMarkup(int $quantity, Rate $markup): self
    {
        return $this->with(new WholesaleTier($quantity, self::markedUp($this->cost, $markup, $this->mode), $markup));
    }

    /**
     * The unit price of the base tier: one unit, at the markup the list started with.
     */
    public function basePrice(): Money
    {
        return $this->tiers[0]->unitPrice;
    }

    /**
     * The unit price for an order of `$quantity` units: the last tier that quantity reaches.
     *
     * @throws InvalidPricing when the quantity is below 1
     */
    public function unitPriceFor(int $quantity): Money
    {
        return $this->tierFor($quantity)->unitPrice;
    }

    /**
     * The unit price of `$quantity` units times that quantity.
     *
     * @throws InvalidPricing when the quantity is below 1
     * @throws InvalidAmount  when the total leaves the native range
     */
    public function totalFor(int $quantity): Money
    {
        return $this->tierFor($quantity)->unitPrice->multiply($quantity);
    }

    /**
     * The tier an order of `$quantity` units reaches.
     *
     * @throws InvalidPricing when the quantity is below 1
     */
    public function tierFor(int $quantity): WholesaleTier
    {
        if ($quantity < 1) {
            throw InvalidPricing::quantityNotPositive($quantity);
        }

        $reached = $this->tiers[0];

        foreach ($this->tiers as $tier) {
            if ($tier->minimumQuantity <= $quantity) {
                $reached = $tier;
            }
        }

        return $reached;
    }

    private function with(WholesaleTier $tier): self
    {
        $last = array_last($this->tiers);

        if ($tier->minimumQuantity <= $last->minimumQuantity) {
            throw InvalidPricing::tiersOutOfOrder($tier->minimumQuantity, $last->minimumQuantity);
        }

        if ($tier->unitPrice->isGreaterThan($last->unitPrice)) {
            throw InvalidPricing::tierRaisesPrice($tier->minimumQuantity, $tier->unitPrice->toDecimal(), $last->unitPrice->toDecimal());
        }

        return new self($this->cost, $this->mode, [...$this->tiers, $tier]);
    }

    private static function markedUp(Money $cost, Rate $markup, RoundingMode $mode): Money
    {
        $unitPrice = MarkedUpPrice::of($cost, $markup, $mode);

        if (!$unitPrice->isPositive()) {
            throw InvalidPricing::unitPriceNotPositive($unitPrice->toDecimal());
        }

        return $unitPrice;
    }
}
