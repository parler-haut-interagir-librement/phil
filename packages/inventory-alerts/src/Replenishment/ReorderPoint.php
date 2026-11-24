<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Internal\Check;

/**
 * The inventory position at which to order: what the lead time will use, plus the safety stock.
 */
final class ReorderPoint
{
    /**
     * average daily demand × lead time + safety stock, in whole units, rounded up.
     */
    public static function of(float $averageDailyDemand, float $leadTimeDays, int $safetyStock = 0): int
    {
        Check::quantity('average daily demand', $averageDailyDemand);
        Check::quantity('lead time', $leadTimeDays, Check::MAX_DAYS);
        Check::units('safety stock', $safetyStock);

        return Check::ceil($averageDailyDemand * $leadTimeDays) + $safetyStock;
    }

    /**
     * The reorder point of a demand history whose periods are days, with its statistical safety stock.
     */
    public static function fromHistory(DemandHistory $history, float $leadTimeDays, ServiceLevel $serviceLevel, float $leadTimeStandardDeviation = 0.0): int
    {
        return self::of($history->mean(), $leadTimeDays, SafetyStock::fromHistory($history, $serviceLevel, $leadTimeDays, $leadTimeStandardDeviation));
    }
}
