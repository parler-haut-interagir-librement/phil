<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Retention;

use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcMonth;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * How long daily counters are kept before only their monthly totals remain: 13 full months by default, so that a month
 * can be compared with the same month a year before.
 */
final readonly class CounterRetention
{
    public const int DEFAULT_MONTHS = 13;

    public function __construct(
        public int $months = self::DEFAULT_MONTHS,
    ) {
        if ($months < 1) {
            throw InvalidValue::because(\sprintf('Daily counters are kept at least one month; %d was given.', $months));
        }
    }

    /**
     * The first day whose daily counters are kept: the first of a month, so that only whole months are rolled up.
     */
    public function firstDailyDay(UtcDay $today): UtcDay
    {
        return UtcMonth::of($today)->minus($this->months)->firstDay();
    }
}
