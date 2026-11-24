<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use Override;
use Phil\CommerceCatalogBundle\Application\Contract\ProductCosts;
use Phil\CommerceCatalogBundle\Application\Contract\UnitCosts;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\Money\Money;

/**
 * `ProductCosts` on the catalogue and a source of unit costs: a composite product costs the sum of its parts, each part's
 * unit cost times its units, and nothing is known of it while one part's cost is unknown; any other SKU costs what the
 * source says. Without a source, no cost is known. One lookup of the product, then one of each part's cost.
 */
final readonly class CompositeCosts implements ProductCosts
{
    public function __construct(
        private ProductRepository $products,
        private ?UnitCosts $units = null,
    ) {
    }

    #[Override]
    public function costOf(string $sku): ?Money
    {
        if (!$this->units instanceof UnitCosts) {
            return null;
        }

        $product = $this->products->find($sku);

        if ($product instanceof Product && $product->isFamily()) {
            return null;
        }

        if (!$product instanceof Product || !$product->isComposite()) {
            return $this->units->unitCost($sku);
        }

        $total = null;

        foreach ($product->components() as $component) {
            $cost = $this->units->unitCost($component->sku);

            if (!$cost instanceof Money) {
                return null;
            }

            if ($total instanceof Money && !$cost->currency->equals($total->currency)) {
                throw CatalogRefused::currency(\sprintf('The parts of %s', $sku), $total->currency->code, $cost->currency->code);
            }

            $part = $cost->multiply($component->quantity);
            $total = $total?->add($part) ?? $part;
        }

        return $total;
    }
}
