<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\LowStock;

use Phil\InventoryAlerts\Contract\LowStockStateStore;
use Phil\InventoryAlerts\Contract\LowStockThresholds;
use Phil\InventoryAlerts\Contract\StaffNotifier;
use Phil\InventoryAlerts\Contract\StockLevelSource;
use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;
use Phil\InventoryAlerts\Stock\StockLevel;
use Phil\InventoryAlerts\Stock\StockMovement;

/**
 * Reads the stock after movements and tells the staff of each location which SKUs became low or ran out there. A
 * SKU is told again only when its situation changes — from low to out, or low again after it rose above its
 * threshold. It keeps no state of its own.
 */
final readonly class LowStockMonitor
{
    public const int MAX_MOVEMENTS = 10_000;

    public function __construct(
        private StockLevelSource $stock,
        private LowStockThresholds $thresholds,
        private LowStockStateStore $states,
        private StaffNotifier $notifier,
    ) {
    }

    /**
     * Checks the SKUs and locations of the movements, notifies one digest per location with something new, and
     * remembers each situation once its digest is sent.
     *
     * @param iterable<StockMovement> $movements at most 10,000; of several movements of a SKU at a location, the last
     *                                           one names the alert, at the place of the first
     *
     * @return list<LowStockDigest> the digests sent, by location in the order of their first alert
     */
    public function record(iterable $movements): array
    {
        $latest = [];
        $count = 0;

        foreach ($movements as $movement) {
            if (++$count > self::MAX_MOVEMENTS) {
                throw InvalidInput::because('list of movements', 'must have at most 10,000 movements');
            }

            $latest[$movement->sku."\0".$movement->location] = $movement;
        }

        $alerts = [];

        foreach ($latest as $movement) {
            $alert = $this->check($movement);

            if ($alert instanceof LowStockAlert) {
                $alerts[$alert->location][] = $alert;
            }
        }

        $digests = [];

        foreach ($alerts as $locationAlerts) {
            $digest = new LowStockDigest($locationAlerts[0]->location, $locationAlerts);
            $this->notifier->notify($digest);

            foreach ($locationAlerts as $alert) {
                $this->states->remember($alert->sku, $alert->location, $alert->situation);
            }

            $digests[] = $digest;
        }

        return $digests;
    }

    private function check(StockMovement $movement): ?LowStockAlert
    {
        $threshold = $this->thresholds->threshold($movement->sku, $movement->location);

        if (null === $threshold) {
            return null;
        }

        Check::units('low-stock threshold', $threshold);

        $level = $this->stock->level($movement->sku, $movement->location) ?? StockLevel::empty($movement->sku, $movement->location);
        $available = $level->available();
        $situation = LowStockSituation::of($available, $threshold);
        $told = $this->states->situation($movement->sku, $movement->location);

        if (!$situation instanceof LowStockSituation) {
            if ($told instanceof LowStockSituation) {
                $this->states->forget($movement->sku, $movement->location);
            }

            return null;
        }

        if ($situation === $told) {
            return null;
        }

        return new LowStockAlert($movement->sku, $movement->location, $situation, $available, $threshold, $movement);
    }
}
