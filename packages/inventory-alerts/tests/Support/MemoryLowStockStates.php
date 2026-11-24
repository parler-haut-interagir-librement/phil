<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Support;

use Phil\InventoryAlerts\Contract\LowStockStateStore;
use Phil\InventoryAlerts\LowStock\LowStockSituation;

/**
 * The situations told to the staff, kept in memory, with the number of forgets.
 */
final class MemoryLowStockStates implements LowStockStateStore
{
    public int $forgets = 0;

    /** @var array<string, LowStockSituation> */
    public array $situations = [];

    public function situation(string $sku, string $location): ?LowStockSituation
    {
        return $this->situations[$sku."\0".$location] ?? null;
    }

    public function remember(string $sku, string $location, LowStockSituation $situation): void
    {
        $this->situations[$sku."\0".$location] = $situation;
    }

    public function forget(string $sku, string $location): void
    {
        ++$this->forgets;
        unset($this->situations[$sku."\0".$location]);
    }
}
