<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Infrastructure\Format\CsvFormat;
use Phil\ReportingBundle\Infrastructure\Format\JsonFormat;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The services the tests share, over the fixtures: a clock frozen on 8 November 2026 at 10:00 in Toronto.
 */
final class Reporting
{
    public const string NOW = '2026-11-08 10:00:00 America/Toronto';

    public static function periods(): Periods
    {
        return new Periods(new MockClock(self::NOW), 'America/Toronto');
    }

    public static function sources(?OrdersSource $orders = null): Sources
    {
        $orders ??= new OrdersSource();

        return new Sources(
            new ServiceLocator(['orders' => static fn (): OrdersSource => $orders]),
            new ServiceLocator(['visits' => static fn (): VisitsSource => new VisitsSource()]),
        );
    }

    public static function reports(int $maxRows = 100): Reports
    {
        return new Reports(
            new ServiceLocator(['sales' => static fn (): SalesReport => new SalesReport()]),
            new ServiceLocator(['csv' => static fn (): CsvFormat => new CsvFormat(), 'json' => static fn (): JsonFormat => new JsonFormat()]),
            $maxRows,
        );
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     */
    public static function request(array $parameters = []): ReportRequest
    {
        $zone = new DateTimeZone('America/Toronto');

        return new ReportRequest(new Period(new DateTimeImmutable('2026-10-01', $zone), new DateTimeImmutable('2026-11-01', $zone)), $parameters);
    }
}
