<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Functional\App;

use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\UsageMeter;
use Psr\Clock\ClockInterface;

/**
 * The application's usage meter: 40 tokens and a budget of 1 000 micros a month, counted in memory.
 */
final class Quotas
{
    private function __construct()
    {
    }

    public static function meter(ClockInterface $clock): UsageMeter
    {
        return new UsageMeter(new Meters(new Meter('ai_tokens', CalendarPeriod::month(), Limit::of(40)), new Meter('ai_budget', CalendarPeriod::month(), Limit::of(1_000))), new InMemoryUsageStore(), $clock);
    }
}
