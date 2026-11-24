<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Support;

use Phil\InventoryAlerts\Contract\LowStockThresholds;
use Phil\InventoryAlerts\Contract\StockLevelSource;
use Phil\InventoryAlerts\Stock\StockLevel;

/**
 * The stock levels and thresholds of a test, kept in memory, with the number of reads.
 */
final class MemoryStock implements StockLevelSource, LowStockThresholds
{
    public int $reads = 0;

    /** @var array<string, StockLevel> */
    private array $levels = [];

    /** @var array<string, int> */
    private array $thresholds = [];

    public function set(StockLevel $level): self
    {
        $this->levels[$level->sku."\0".$level->location] = $level;

        return $this;
    }

    public function watch(string $sku, string $location, int $threshold): self
    {
        $this->thresholds[$sku."\0".$location] = $threshold;

        return $this;
    }

    public function level(string $sku, string $location): ?StockLevel
    {
        ++$this->reads;

        return $this->levels[$sku."\0".$location] ?? null;
    }

    public function threshold(string $sku, string $location): ?int
    {
        return $this->thresholds[$sku."\0".$location] ?? null;
    }
}
