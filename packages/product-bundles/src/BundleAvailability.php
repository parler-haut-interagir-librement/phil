<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\ProductBundles\Contract\ComponentStock;

/**
 * The bundles a shop can sell: for each component, its available units divided by the units one bundle holds, rounded
 * down; the smallest of these is the bundle's stock, never below zero. Eleven decks and twenty rule books make five
 * bundles of two decks and one book; the decks set the limit.
 */
final readonly class BundleAvailability
{
    public function __construct(
        private ComponentStock $stock,
    ) {
    }

    public function of(ProductBundle $bundle): BundleStock
    {
        $quantity = null;
        $limitedBy = $bundle->components[0]->sku;

        foreach ($bundle->components as $component) {
            $bundles = intdiv(max(0, $this->stock->available($component->sku)), $component->quantity);

            if (null === $quantity || $bundles < $quantity) {
                $quantity = $bundles;
                $limitedBy = $component->sku;
            }
        }

        return new BundleStock($bundle->sku, $quantity ?? 0, $limitedBy);
    }
}
