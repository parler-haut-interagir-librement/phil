<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Message\RunReport;
use Phil\ReportingBundle\Application\Message\RunScheduledReport;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\ReportSchedule;
use Phil\ReportingBundle\Application\Report\Schedules;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schedules::class)]
#[CoversClass(ReportSchedule::class)]
#[CoversClass(RunReport::class)]
#[CoversClass(RunScheduledReport::class)]
final class SchedulesTest extends TestCase
{
    public function testTheSchedulesOfTheConfiguration(): void
    {
        $schedules = Schedules::fromConfig([
            'monthly_sales' => ['report' => 'sales', 'format' => 'csv', 'every' => '1 month', 'from' => '2026-11-01 06:00', 'period' => 'last_month', 'delivery' => 'mail', 'recipients' => ['ada@example.com'], 'parameters' => ['status' => 'paid']],
            'daily' => ['report' => 'sales', 'format' => 'json', 'every' => '1 day', 'from' => null, 'period' => 'yesterday', 'delivery' => null, 'recipients' => [], 'parameters' => []],
        ], Reporting::periods());

        self::assertEquals([
            'monthly_sales' => new ReportSchedule('monthly_sales', 'sales', 'csv', '1 month', '2026-11-01 06:00', PeriodPreset::LastMonth, 'mail', ['ada@example.com'], ['status' => 'paid']),
            'daily' => new ReportSchedule('daily', 'sales', 'json', '1 day', null, PeriodPreset::Yesterday, null),
        ], $schedules->all());
        self::assertEquals(new ReportJob('sales', 'csv', Reporting::request(['status' => 'paid']), 'mail', ['ada@example.com']), $schedules->job('monthly_sales'));
        self::assertSame('2026-11-07', $schedules->job('daily')->request->period->start->format('Y-m-d'));
        self::assertNull($schedules->job('daily')->delivery);
        self::assertInstanceOf(ReportRequest::class, $schedules->job('daily')->request);
        self::assertSame('abc', new RunReport('abc')->job);
        self::assertSame('daily', new RunScheduledReport('daily')->schedule);

        $this->expectExceptionObject(NotFound::of('schedule', 'weekly'));

        $schedules->job('weekly');
    }
}
