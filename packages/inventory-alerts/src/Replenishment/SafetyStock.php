<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Replenishment;

use Phil\InventoryAlerts\Internal\Check;

/**
 * The units kept against a demand or a delivery that runs late, in whole units, rounded up.
 */
final class SafetyStock
{
    /**
     * The simple method: what the worst day and the longest lead time would use, less what the average ones use.
     *
     * (max daily demand × max lead time) − (average daily demand × average lead time), never under 0.
     */
    public static function simple(float $maxDailyDemand, float $maxLeadTimeDays, float $averageDailyDemand, float $averageLeadTimeDays): int
    {
        Check::quantity('maximum daily demand', $maxDailyDemand);
        Check::quantity('maximum lead time', $maxLeadTimeDays, Check::MAX_DAYS);
        Check::quantity('average daily demand', $averageDailyDemand);
        Check::quantity('average lead time', $averageLeadTimeDays, Check::MAX_DAYS);

        return max(0, Check::ceil($maxDailyDemand * $maxLeadTimeDays - $averageDailyDemand * $averageLeadTimeDays));
    }

    /**
     * The statistical method, for a service level: z × √(lead time × σ(demand)² + average demand² × σ(lead time)²),
     * which is z × σ(demand) × √(lead time) when the lead time does not vary.
     */
    public static function statistical(
        ServiceLevel $serviceLevel,
        float $demandStandardDeviation,
        float $leadTimeDays,
        float $averageDailyDemand = 0.0,
        float $leadTimeStandardDeviation = 0.0,
    ): int {
        Check::quantity('standard deviation of the demand', $demandStandardDeviation);
        Check::quantity('lead time', $leadTimeDays, Check::MAX_DAYS);
        Check::quantity('average daily demand', $averageDailyDemand);
        Check::quantity('standard deviation of the lead time', $leadTimeStandardDeviation, Check::MAX_DAYS);

        $variance = $leadTimeDays * $demandStandardDeviation ** 2 + $averageDailyDemand ** 2 * $leadTimeStandardDeviation ** 2;

        return Check::ceil($serviceLevel->z() * sqrt($variance));
    }

    /**
     * The statistical method with the mean and the standard deviation of a demand history, its periods being days.
     */
    public static function fromHistory(DemandHistory $history, ServiceLevel $serviceLevel, float $leadTimeDays, float $leadTimeStandardDeviation = 0.0): int
    {
        return self::statistical($serviceLevel, $history->standardDeviation(), $leadTimeDays, $history->mean(), $leadTimeStandardDeviation);
    }
}
