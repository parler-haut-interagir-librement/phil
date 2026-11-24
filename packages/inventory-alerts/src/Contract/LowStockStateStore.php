<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Contract;

use Phil\InventoryAlerts\LowStock\LowStockSituation;

/**
 * The situation last told to the staff for each SKU at each location, so an alert is sent again only when the
 * situation changes.
 */
interface LowStockStateStore
{
    public function situation(string $sku, string $location): ?LowStockSituation;

    public function remember(string $sku, string $location, LowStockSituation $situation): void;

    public function forget(string $sku, string $location): void;
}
