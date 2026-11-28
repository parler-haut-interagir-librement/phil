<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;
use Phil\Pricing\Exception\UndefinedRate;
use Phil\Pricing\MarginCalculator;
use Phil\ProductBundles\Contract\ComponentCosts;
use RoundingMode;

/**
 * The cost of a bundle composed from its components' unit costs, and its margin.
 *
 * A bundle holds no stock of its own, so it has no cost of its own: each component costs what the stock valuation
 * says now — weighted average or first in, first out, as the shop chose —, times the units one bundle holds. One
 * unknown cost leaves the whole cost unknown, never a cost too low.
 */
final readonly class ComposedCost
{
    public function __construct(
        private ComponentCosts $costs,
    ) {
    }

    public function of(ProductBundle $bundle): BundleCost
    {
        $components = [];
        $known = [];
        $unknown = [];

        foreach ($bundle->components as $component) {
            $unitCost = $this->costs->unitCost($component->sku);
            $cost = $unitCost?->multiply($component->quantity);
            $components[] = new ComponentCost($component->sku, $component->quantity, $unitCost, $cost);

            if ($cost instanceof Money) {
                $known[] = $cost;
            } else {
                $unknown[] = $component->sku;
            }
        }

        return new BundleCost($bundle->sku, [] === $unknown ? Money::sum(...$known) : null, $components, $unknown);
    }

    /**
     * The margin of a quote over the composed cost; null when the cost is unknown or in another currency than the price.
     */
    public function margin(BundleQuote $quote, BundleCost $cost, RoundingMode $rounding = RoundingMode::HalfAwayFromZero): ?BundleMargin
    {
        $unitCost = $cost->unitCost;

        if (!$unitCost instanceof Money || !$unitCost->currency->equals($quote->price->currency)) {
            return null;
        }

        $calculator = new MarginCalculator();

        try {
            $rate = $calculator->marginRate($quote->price, $unitCost, $rounding);
        } catch (UndefinedRate) {
            $rate = null;
        }

        return new BundleMargin($quote->sku, $quote->price, $unitCost, $calculator->margin($quote->price, $unitCost), $rate);
    }
}
