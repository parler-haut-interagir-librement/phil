<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Contract;

use Phil\Money\Money;

/**
 * What one unit of a component costs the shop now, as its stock valuation says.
 */
interface ComponentCosts
{
    /**
     * The unit cost, null when the SKU was never received at a known cost.
     */
    public function unitCost(string $sku): ?Money;
}
