<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Tests\Benchmark;

use DateTimeImmutable;
use Phil\InventoryAlerts\LowStock\LowStockMonitor;
use Phil\InventoryAlerts\Replenishment\ReplenishmentPlanner;
use Phil\InventoryAlerts\Replenishment\ReplenishmentRule;
use Phil\InventoryAlerts\Stock\StockLevel;
use Phil\InventoryAlerts\Stock\StockMovement;
use Phil\InventoryAlerts\Tests\Support\MemoryLowStockStates;
use Phil\InventoryAlerts\Tests\Support\MemoryStock;
use Phil\InventoryAlerts\Tests\Support\RecordingStaffNotifier;

/**
 * The hot paths on 10,000 SKUs at two locations: the purchase proposals of a night run, and the low-stock check of a
 * day of movements.
 */
final readonly class AlertsBench
{
    private MemoryStock $stock;

    /** @var list<ReplenishmentRule> */
    private array $rules;

    /** @var list<StockMovement> */
    private array $movements;

    public function __construct()
    {
        $this->stock = new MemoryStock();
        $rules = [];
        $movements = [];
        $at = new DateTimeImmutable('2026-11-21 09:00');

        for ($i = 0; $i < 5_000; ++$i) {
            foreach (['store', 'depot'] as $location) {
                $this->stock->set(new StockLevel('S'.$i, $location, $i % 40, $i % 3))->watch('S'.$i, $location, 5);
                $rules[] = 0 === $i % 2
                    ? ReplenishmentRule::minMax('S'.$i, $location, 'supplier'.($i % 25), 10, 60, 6)
                    : ReplenishmentRule::fixedQuantity('S'.$i, $location, 'supplier'.($i % 25), 12, 48);
                $movements[] = new StockMovement('m'.$i.$location, 'S'.$i, $location, -1, $at);
            }
        }

        $this->rules = $rules;
        $this->movements = $movements;
    }

    public function benchProposePurchaseOrders(): void
    {
        new ReplenishmentPlanner($this->stock)->propose($this->rules, new DateTimeImmutable('2026-11-21 02:00'));
    }

    public function benchCheckLowStock(): void
    {
        new LowStockMonitor($this->stock, $this->stock, new MemoryLowStockStates(), new RecordingStaffNotifier())->record($this->movements);
    }
}
