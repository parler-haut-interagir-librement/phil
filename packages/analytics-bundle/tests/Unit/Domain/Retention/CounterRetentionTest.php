<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Retention;

use Phil\AnalyticsBundle\Domain\Retention\CounterRetention;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CounterRetention::class)]
final class CounterRetentionTest extends TestCase
{
    public function testThirteenWholeMonthsAreKeptByDefault(): void
    {
        self::assertSame(13, new CounterRetention()->months);
        self::assertSame('2025-10-01', new CounterRetention()->firstDailyDay(UtcDay::fromString('2026-11-09'))->toString());
    }

    public function testTheMonthsAreTheApplicationsToChoose(): void
    {
        self::assertSame('2026-10-01', new CounterRetention(1)->firstDailyDay(UtcDay::fromString('2026-11-30'))->toString());
    }

    public function testNoMonthIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Daily counters are kept at least one month; 0 was given.');

        new CounterRetention(0);
    }
}
