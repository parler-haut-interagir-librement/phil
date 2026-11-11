<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(PeriodPreset::class)]
#[CoversClass(Periods::class)]
#[CoversClass(NotFound::class)]
final class PeriodsTest extends TestCase
{
    #[DataProvider('presets')]
    public function testEachPreset(PeriodPreset $preset, string $period, string $previous): void
    {
        $now = new DateTimeImmutable(Reporting::NOW);

        self::assertSame($period, $this->read($preset->period($now)));
        self::assertSame($previous, $this->read($preset->previous($now)));
        self::assertSame($period, $this->read(Reporting::periods()->period($preset)));
        self::assertSame($previous, $this->read(Reporting::periods()->previous($preset)));
    }

    /**
     * @return iterable<array{PeriodPreset, string, string}>
     */
    public static function presets(): iterable
    {
        yield [PeriodPreset::Today, '2026-11-08 2026-11-09', '2026-11-07 2026-11-08'];
        yield [PeriodPreset::Yesterday, '2026-11-07 2026-11-08', '2026-11-06 2026-11-07'];
        yield [PeriodPreset::Last7Days, '2026-11-02 2026-11-09', '2026-10-26 2026-11-02'];
        yield [PeriodPreset::Last30Days, '2026-10-10 2026-11-09', '2026-09-10 2026-10-10'];
        yield [PeriodPreset::ThisMonth, '2026-11-01 2026-12-01', '2026-10-01 2026-11-01'];
        yield [PeriodPreset::LastMonth, '2026-10-01 2026-11-01', '2026-09-01 2026-10-01'];
        yield [PeriodPreset::Last12Months, '2025-12-01 2026-12-01', '2024-12-01 2025-12-01'];
        yield [PeriodPreset::ThisYear, '2026-01-01 2027-01-01', '2025-01-01 2026-01-01'];
        yield [PeriodPreset::LastYear, '2025-01-01 2026-01-01', '2024-01-01 2025-01-01'];
    }

    public function testThePeriodsAreCountedInTheTimeZone(): void
    {
        $periods = new Periods(new MockClock('2026-11-08 03:00:00 UTC'), 'America/Toronto');
        $today = $periods->period(PeriodPreset::Today);

        self::assertSame('2026-11-07T00:00:00-05:00', $today->start->format(\DATE_ATOM));
        self::assertSame('America/Toronto', $today->start->getTimezone()->getName());
        self::assertSame('2026-11-08 2026-11-09', $this->read(new Periods(new MockClock('2026-11-08 03:00:00 UTC'))->period(PeriodPreset::Today)));
        self::assertSame('2026-01-31 2026-02-01', $this->read(PeriodPreset::Today->period(new DateTimeImmutable('2026-01-31 23:59', new DateTimeZone('Asia/Tokyo')))));
    }

    public function testAPresetByName(): void
    {
        self::assertSame(PeriodPreset::LastMonth, Reporting::periods()->preset('last_month'));

        $this->expectExceptionObject(NotFound::of('period', 'fortnight'));

        Reporting::periods()->preset('fortnight');
    }

    private function read(Period $period): string
    {
        return $period->start->format('Y-m-d').' '.$period->end->format('Y-m-d');
    }
}
