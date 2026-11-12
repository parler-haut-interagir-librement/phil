<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Retention;

use Phil\AnalyticsBundle\Application\Contract\CounterRollup;
use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;

/**
 * Daily counters past their retention, rolled up into monthly totals. Run once a day: the first day kept moves on the
 * first of each month, so the other days roll up nothing.
 */
final readonly class CounterRetentionRun
{
    public function __construct(
        private CounterRollup $rollup,
        private CounterRetention $retention,
        private SmallNumberThreshold $threshold,
    ) {
    }

    /**
     * @return UtcDay the first day whose daily counters are kept
     */
    public function runFor(UtcDay $today): UtcDay
    {
        $firstDailyDay = $this->retention->firstDailyDay($today);
        $this->rollup->rollUpBefore($firstDailyDay, $this->threshold);

        return $firstDailyDay;
    }
}
