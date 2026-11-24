<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\LowStock;

use Phil\InventoryAlerts\Stock\StockMovement;

/**
 * A SKU low or out at a location, with its available units, its threshold and the movement that brought it there.
 */
final readonly class LowStockAlert
{
    public function __construct(
        public string $sku,
        public string $location,
        public LowStockSituation $situation,
        public int $available,
        public int $threshold,
        public StockMovement $movement,
    ) {
    }
}
