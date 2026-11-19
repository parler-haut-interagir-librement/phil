<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit\Period;

use DateTimeImmutable;
use Phil\UsageMeter\Exception\InvalidUsage;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\CalendarUnit;
use Phil\UsageMeter\Period\Window;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarPeriod::class)]
#[CoversClass(Window::class)]
final class CalendarPeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{CalendarPeriod, string, string, string}>
     */
    public static function periods(): iterable
    {
        yield 'day' => [CalendarPeriod::day(), '2026-03-14T10:30:00+00:00', '2026-03-14T00:00:00+00:00', '2026-03-15T00:00:00+00:00'];
        yield 'day at midnight' => [CalendarPeriod::day(), '2026-03-14T00:00:00+00:00', '2026-03-14T00:00:00+00:00', '2026-03-15T00:00:00+00:00'];
        yield 'week from Monday' => [CalendarPeriod::week(), '2026-03-14T10:30:00+00:00', '2026-03-09T00:00:00+00:00', '2026-03-16T00:00:00+00:00'];
        yield 'week on Sunday' => [CalendarPeriod::week(), '2026-03-15T23:59:59+00:00', '2026-03-09T00:00:00+00:00', '2026-03-16T00:00:00+00:00'];
        yield 'ISO week across years' => [CalendarPeriod::week(), '2027-01-01T12:00:00+00:00', '2026-12-28T00:00:00+00:00', '2027-01-04T00:00:00+00:00'];
        yield 'month' => [CalendarPeriod::month(), '2026-03-14T10:30:00+00:00', '2026-03-01T00:00:00+00:00', '2026-04-01T00:00:00+00:00'];
        yield 'last instant of a month' => [CalendarPeriod::month(), '2026-01-31T23:59:59+00:00', '2026-01-01T00:00:00+00:00', '2026-02-01T00:00:00+00:00'];
        yield 'February' => [CalendarPeriod::month(), '2028-02-29T10:00:00+00:00', '2028-02-01T00:00:00+00:00', '2028-03-01T00:00:00+00:00'];
        yield 'year' => [CalendarPeriod::year(), '2026-03-14T10:30:00+00:00', '2026-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00'];
        yield 'month in a time zone' => [CalendarPeriod::month('America/Toronto'), '2026-04-01T02:00:00+00:00', '2026-03-01T00:00:00-05:00', '2026-04-01T00:00:00-04:00'];
        yield 'day across a change of offset' => [CalendarPeriod::day('America/Toronto'), '2026-03-08T12:00:00+00:00', '2026-03-08T00:00:00-05:00', '2026-03-09T00:00:00-04:00'];
    }

    #[DataProvider('periods')]
    public function testThePeriodHoldingAMomentStartsAtLocalMidnight(CalendarPeriod $period, string $at, string $start, string $end): void
    {
        $window = $period->windowAt(new DateTimeImmutable($at));

        self::assertSame($start, $window->start->format(\DATE_ATOM));
        self::assertSame($end, $window->end->format(\DATE_ATOM));
        self::assertSame($start, $window->counterStart->format(\DATE_ATOM));
        self::assertSame($end, $window->counterEnd->format(\DATE_ATOM));
    }

    public function testTheAnchorIsIgnored(): void
    {
        $window = CalendarPeriod::month()->windowAt(new DateTimeImmutable('2026-03-14T10:30:00+00:00'), new DateTimeImmutable('2026-01-20'));

        self::assertSame('2026-03-01T00:00:00+00:00', $window->start->format(\DATE_ATOM));
    }

    public function testTheUnitAndZoneAreKept(): void
    {
        $period = CalendarPeriod::week('Europe/Paris');

        self::assertSame(CalendarUnit::Week, $period->unit);
        self::assertSame('Europe/Paris', $period->timezone->getName());
        self::assertSame('UTC', new CalendarPeriod(CalendarUnit::Day)->timezone->getName());
        self::assertSame(CalendarUnit::Day, CalendarPeriod::day()->unit);
        self::assertSame(CalendarUnit::Month, CalendarPeriod::month()->unit);
        self::assertSame(CalendarUnit::Year, CalendarPeriod::year()->unit);
        self::assertSame('UTC', CalendarPeriod::year()->timezone->getName());
    }

    public function testAnUnknownZoneIsRefused(): void
    {
        $this->expectException(InvalidUsage::class);
        $this->expectExceptionMessageIsOrContains('"Mars/Olympus" is not a time zone.');

        CalendarPeriod::month('Mars/Olympus');
    }
}
