<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * Whole bundles returned: the amount to refund, its share per component for a collection's taxes, the units to put
 * back in stock, and the sold bundle as it stands after the return.
 */
final readonly class BundleRefund
{
    /**
     * @param list<Money>     $shares  by component, in the bundle's order, adding up to the amount
     * @param list<StockDraw> $restock
     */
    public function __construct(
        public int $bundles,
        public Money $amount,
        public array $shares,
        public array $restock,
        public SoldBundle $after,
    ) {
    }
}
