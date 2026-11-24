<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

use Phil\InventoryAlerts\Stock\StockLevel;

/**
 * Where the library reads the stock: the warehouse or point-of-sale records of the application, through an adapter.
 */
interface StockLevelSource
{
    /**
     * The level of a SKU at a location, or null when the location never held it.
     */
    public function level(string $sku, string $location): ?StockLevel;
}
