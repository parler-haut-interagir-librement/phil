<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Report;

use Phil\AnalyticsBundle\Domain\Report\ReportCadence;
use Phil\AnalyticsBundle\Domain\Report\ReportPeriod;
use Phil\AnalyticsBundle\Domain\ValueObject\UtcDay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportPeriod::class)]
final class ReportPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ReportCadence, string, string}>
     */
    public static function periods(): iterable
    {
        yield 'a Monday' => ['2026-11-09', ReportCadence::Weekly, '2026-11-02', '2026-11-08'];
        yield 'a Sunday' => ['2026-11-15', ReportCadence::Weekly, '2026-11-02', '2026-11-08'];
        yield 'a Sunday of an ISO week 53' => ['2027-01-03', ReportCadence::Weekly, '2026-12-21', '2026-12-27'];
        yield 'a day of the first ISO week, still in December' => ['2025-12-30', ReportCadence::Weekly, '2025-12-22', '2025-12-28'];
        yield 'a month' => ['2026-11-09', ReportCadence::Monthly, '2026-10-01', '2026-10-31'];
        yield 'over the new year' => ['2026-01-31', ReportCadence::Monthly, '2025-12-01', '2025-12-31'];
    }

    #[DataProvider('periods')]
    public function testTheLastCompletePeriod(string $today, ReportCadence $cadence, string $from, string $to): void
    {
        $period = ReportPeriod::lastCompleteBefore(UtcDay::fromString($today), $cadence);

        self::assertSame($cadence, $period->cadence);
        self::assertSame($from, $period->from->toString());
        self::assertSame($to, $period->to->toString());
    }
}
