<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

/**
 * The low-stock threshold of each SKU at each location, set by the staff or taken from the reorder point.
 */
interface LowStockThresholds
{
    /**
     * The number of available units at or under which the SKU is low at the location, or null when nobody watches it
     * there.
     */
    public function threshold(string $sku, string $location): ?int;
}
