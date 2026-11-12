<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Contract;

use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;

/**
 * Daily counters past their retention, replaced by monthly totals.
 */
interface CounterRollup
{
    /**
     * In one transaction, adds the daily counters before this day to their monthly totals and deletes them. The
     * monthly total of an open dimension ({@see MeasureNames::OPEN_DIMENSIONS}) under the threshold is not kept: a rare
     * search term or host is forgotten.
     */
    public function rollUpBefore(UtcDay $firstDailyDay, SmallNumberThreshold $threshold): void;

    /**
     * The monthly totals of one metric and one dimension over an inclusive range of months, summed per value. The
     * small-number rule is applied by the caller, on read.
     *
     * @return array<string, int>
     */
    public function monthlyCounts(SiteId $site, string $metric, string $dimension, UtcMonth $from, UtcMonth $to): array;
}
