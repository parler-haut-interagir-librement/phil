<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Tests\Unit;

use DateTimeImmutable;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Period\BillingCycle;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\CalendarUnit;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeterBundle\Infrastructure\Configuration\ConfiguredMeters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredMeters::class)]
final class ConfiguredMetersTest extends TestCase
{
    public function testEachPeriodAndLimitIsBuilt(): void
    {
        $meters = ConfiguredMeters::fromConfiguration([
            'days' => $this->meter(...['limit' => 3, 'calendar' => 'day', 'timezone' => 'Europe/Paris']),
            'weeks' => $this->meter(...['limit' => '0', 'calendar' => 'week']),
            'months' => $this->meter(...['limit' => 'unlimited', 'calendar' => 'month', 'timezone' => 'America/Toronto']),
            'years' => $this->meter(...['limit' => 12, 'calendar' => 'year']),
            'requests' => $this->meter(...['limit' => 600, 'rolling' => 3600, 'buckets' => 60]),
            'tokens' => $this->meter(...['limit' => 50000, 'billing' => '2026-01-31T10:30:00-05:00', 'months' => 3]),
            7 => $this->meter(...['limit' => 1, 'rolling' => 60]),
        ]);

        self::assertSame(['days', 'weeks', 'months', 'years', 'requests', 'tokens', '7'], $meters->names());

        $days = $meters->get('days');
        self::assertInstanceOf(CalendarPeriod::class, $days->period);
        self::assertSame(CalendarUnit::Day, $days->period->unit);
        self::assertSame('Europe/Paris', $days->period->timezone->getName());
        self::assertSame(3, $days->limit->value);

        $weeks = $meters->get('weeks');
        self::assertInstanceOf(CalendarPeriod::class, $weeks->period);
        self::assertSame(CalendarUnit::Week, $weeks->period->unit);
        self::assertSame('UTC', $weeks->period->timezone->getName());
        self::assertSame(0, $weeks->limit->value);

        $months = $meters->get('months');
        self::assertInstanceOf(CalendarPeriod::class, $months->period);
        self::assertSame(CalendarUnit::Month, $months->period->unit);
        self::assertSame('America/Toronto', $months->period->timezone->getName());
        self::assertTrue($months->limit->isUnlimited());

        $years = $meters->get('years');
        self::assertInstanceOf(CalendarPeriod::class, $years->period);
        self::assertSame(CalendarUnit::Year, $years->period->unit);

        $requests = $meters->get('requests');
        self::assertInstanceOf(RollingWindow::class, $requests->period);
        self::assertSame(3600, $requests->period->seconds);
        self::assertSame(60, $requests->period->buckets);
        self::assertSame(600, $requests->limit->value);

        $tokens = $meters->get('tokens');
        self::assertInstanceOf(BillingCycle::class, $tokens->period);
        self::assertSame(3, $tokens->period->months);
        self::assertSame('2026-01-31T10:30:00-05:00', $tokens->period->anchor->format(\DATE_ATOM));

        $seventh = $meters->get('7');
        self::assertInstanceOf(RollingWindow::class, $seventh->period);
        self::assertSame(1, $seventh->period->buckets);
    }

    public function testATimestampAnchorIsRead(): void
    {
        $period = ConfiguredMeters::fromConfiguration(['tokens' => $this->meter(...['limit' => 1, 'billing' => '@1767225600'])])->get('tokens')->period;

        self::assertInstanceOf(BillingCycle::class, $period);
        self::assertEquals(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), $period->anchor);
    }

    public function testBadMetersAreRefused(): void
    {
        $cases = [
            ['names exactly one period', $this->meter(...['limit' => 1])],
            ['names exactly one period', $this->meter(...['limit' => 1, 'calendar' => 'day', 'rolling' => 60])],
            ['names exactly one period', $this->meter(...['limit' => 1, 'calendar' => 'day', 'billing' => '2026-01-01'])],
            ['names exactly one period', $this->meter(...['limit' => 1, 'rolling' => 60, 'billing' => '2026-01-01'])],
            ['got "-1"', $this->meter(...['limit' => -1, 'rolling' => 60])],
            ['got "many"', $this->meter(...['limit' => 'many', 'rolling' => 60])],
            ['got "bool"', $this->meter(...['limit' => true, 'rolling' => 60])],
            ['got "float"', $this->meter(...['limit' => 1.5, 'rolling' => 60])],
            ['is day, week, month or year; got "hour"', $this->meter(...['limit' => 1, 'calendar' => 'hour'])],
            ['is not a date: "soon"', $this->meter(...['limit' => 1, 'billing' => 'soon'])],
            ['is not a time zone', $this->meter(...['limit' => 1, 'calendar' => 'day', 'timezone' => 'Mars/Olympus'])],
            ['buckets that divide it exactly', $this->meter(...['limit' => 1, 'rolling' => 60, 'buckets' => 7])],
            ['lasts 1 to 120 months', $this->meter(...['limit' => 1, 'billing' => '2026-01-01', 'months' => 0])],
        ];

        foreach ($cases as [$message, $meter]) {
            try {
                ConfiguredMeters::fromConfiguration(['bad' => $meter]);
                self::fail($message);
            } catch (InvalidUsage $invalidUsage) {
                self::assertStringContainsString($message, $invalidUsage->getMessage());
            }
        }
    }

    public function testTheMeterIsNamedInTheMessages(): void
    {
        $this->expectExceptionMessageIsOrContains('The meter "exports" names exactly one period: calendar, rolling or billing.');

        ConfiguredMeters::fromConfiguration(['exports' => $this->meter(...['limit' => 1])]);
    }

    /**
     * @return array{limit: mixed, calendar: string|null, timezone: string, rolling: int|null, buckets: int, billing: string|null, months: int}
     */
    private function meter(mixed $limit, ?string $calendar = null, string $timezone = 'UTC', ?int $rolling = null, int $buckets = 1, ?string $billing = null, int $months = 1): array
    {
        return ['limit' => $limit, 'calendar' => $calendar, 'timezone' => $timezone, 'rolling' => $rolling, 'buckets' => $buckets, 'billing' => $billing, 'months' => $months];
    }
}
