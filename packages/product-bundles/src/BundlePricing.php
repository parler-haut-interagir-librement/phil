<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;
use Phil\ProductBundles\Contract\ComponentPrices;
use Phil\ProductBundles\Exception\PriceUnknown;

/**
 * Prices a bundle from its components' prices, and shares its price among them.
 *
 * The share of a component follows what its units sell for apart: a book of 40.00 and dice of 10.00 in a bundle of
 * 45.00 take 36.00 and 9.00. When every component is free apart, the shares follow the quantities. A collection is
 * taxed on these shares, component by component; an assembled bundle keeps them for its reports.
 */
final readonly class BundlePricing
{
    public function __construct(
        private ComponentPrices $prices,
    ) {
    }

    /**
     * @throws PriceUnknown when a component has no price in the bundle's currency
     */
    public function quote(ProductBundle $bundle): BundleQuote
    {
        $unitPrices = [];
        $values = [];

        foreach ($bundle->components as $component) {
            $unitPrice = $this->prices->unitPrice($component->sku, $bundle->currency);

            if (!$unitPrice instanceof Money || !$unitPrice->currency->equals($bundle->currency) || $unitPrice->isNegative()) {
                throw PriceUnknown::of($bundle->sku, $component->sku, $bundle->currency->code);
            }

            $unitPrices[] = $unitPrice;
            $values[] = $unitPrice->multiply($component->quantity);
        }

        $componentsValue = Money::sum(...$values);
        $price = $bundle->price->priceOf($componentsValue);
        $shares = $this->shares($price, $values, $bundle->components);
        $components = [];

        foreach ($bundle->components as $index => $component) {
            $components[] = new QuotedComponent($component->sku, $component->quantity, $unitPrices[$index], $values[$index], $shares[$index]);
        }

        return new BundleQuote($bundle->sku, $componentsValue, $price, $components);
    }

    /**
     * An amount shared among the components by what they sell for apart, or by their quantities when all are free.
     *
     * @param list<Money>           $values
     * @param list<BundleComponent> $components
     *
     * @return list<Money>
     */
    private function shares(Money $amount, array $values, array $components): array
    {
        $ratios = array_map(static fn (Money $value): int => $value->minorUnits, $values);

        if (0 === array_sum($ratios)) {
            $ratios = array_map(static fn (BundleComponent $component): int => $component->quantity, $components);
        }

        return $amount->allocate(...$ratios);
    }
}
