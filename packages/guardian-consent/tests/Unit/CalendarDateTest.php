<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\GuardianConsent\CalendarDate;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\LeapDayRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarDate::class)]
#[CoversClass(InvalidConsent::class)]
#[CoversClass(LeapDayRule::class)]
final class CalendarDateTest extends TestCase
{
    public function testADateIsReadAndWrittenBack(): void
    {
        $date = CalendarDate::fromString('2012-02-29');

        self::assertSame(2012, $date->year);
        self::assertSame(2, $date->month);
        self::assertSame(29, $date->day);
        self::assertSame('2012-02-29', $date->toString());
        self::assertSame('0001-01-01', CalendarDate::fromString('0001-01-01')->toString());
        self::assertSame('9999-12-31', CalendarDate::fromParts(9999, 12, 31)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTexts(): iterable
    {
        yield 'no day' => ['2012-02'];
        yield 'short year' => ['212-02-01'];
        yield 'trailing newline' => ["2012-02-01\n"];
        yield 'leading text' => ['x2012-02-01'];
        yield 'trailing text' => ['2012-02-01x'];
        yield 'slashes' => ['2012/02/01'];
        yield 'single digit month' => ['2012-2-01'];
    }

    #[DataProvider('invalidTexts')]
    public function testATextThatIsNotADateIsRefused(string $text): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains('A date is written YYYY-MM-DD.');

        CalendarDate::fromString($text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function daysNotInTheCalendar(): iterable
    {
        yield '29 February of a common year' => ['2013-02-29'];
        yield '29 February of 1900' => ['1900-02-29'];
        yield 'month 13' => ['2012-13-01'];
        yield 'month 0' => ['2012-00-10'];
        yield 'day 0' => ['2012-01-00'];
        yield '31 April' => ['2012-04-31'];
        yield 'year 0' => ['0000-01-01'];
    }

    #[DataProvider('daysNotInTheCalendar')]
    public function testADayNotInTheCalendarIsRefused(string $text): void
    {
        $this->expectException(InvalidConsent::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('%s is not a day of the calendar.', $text));

        CalendarDate::fromString($text);
    }

    public function testAYearPast9999IsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('10000-01-01 is not a day of the calendar.');

        CalendarDate::fromParts(10000, 1, 1);
    }

    public function testTheDayOfAnInstantDependsOnTheTimeZone(): void
    {
        $instant = new DateTimeImmutable('2026-11-14 03:30:00', new DateTimeZone('UTC'));

        self::assertSame('2026-11-14', CalendarDate::of($instant, new DateTimeZone('UTC'))->toString());
        self::assertSame('2026-11-13', CalendarDate::of($instant, new DateTimeZone('America/Toronto'))->toString());
        self::assertSame('2026-11-14', CalendarDate::of($instant, new DateTimeZone('Europe/Paris'))->toString());
        self::assertSame('2026-11-13', CalendarDate::of($instant, new DateTimeZone('Pacific/Honolulu'))->toString());
        self::assertSame('2026-11-14 00:00:00 -05:00', CalendarDate::fromString('2026-11-14')->startsAt(new DateTimeZone('America/Toronto'))->format('Y-m-d H:i:s P'));
        self::assertSame('2019-03-02 00:00:00 +01:00', CalendarDate::fromString('2019-03-02')->startsAt(new DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s P'));
        self::assertSame('1999-12-31', CalendarDate::fromString('1999-12-31')->toString());
    }

    public function testLeapYears(): void
    {
        self::assertTrue(CalendarDate::isLeapYear(2024));
        self::assertTrue(CalendarDate::isLeapYear(2000));
        self::assertFalse(CalendarDate::isLeapYear(1900));
        self::assertFalse(CalendarDate::isLeapYear(2026));
        self::assertFalse(CalendarDate::isLeapYear(2100));
    }

    public function testAnAnniversaryOf29FebruaryFollowsTheRule(): void
    {
        $leapDay = CalendarDate::fromString('2012-02-29');

        self::assertSame('2026-03-01', $leapDay->anniversary(14)->toString());
        self::assertSame('2026-03-01', $leapDay->anniversary(14, LeapDayRule::FirstOfMarch)->toString());
        self::assertSame('2026-02-28', $leapDay->anniversary(14, LeapDayRule::TwentyEighthOfFebruary)->toString());
        self::assertSame('2028-02-29', $leapDay->anniversary(16, LeapDayRule::TwentyEighthOfFebruary)->toString());
        self::assertSame('2028-02-29', $leapDay->anniversary(16)->toString());
        self::assertSame('2025-02-28', CalendarDate::fromString('2011-02-28')->anniversary(14)->toString());
        self::assertSame('2027-01-29', CalendarDate::fromString('2013-01-29')->anniversary(14)->toString());
        self::assertSame('2026-03-29', CalendarDate::fromString('2012-03-29')->anniversary(14)->toString());
    }

    public function testTheWholeYearsCompleted(): void
    {
        $birth = CalendarDate::fromString('2012-11-14');

        self::assertSame(13, $birth->yearsUntil(CalendarDate::fromString('2026-11-13')));
        self::assertSame(14, $birth->yearsUntil(CalendarDate::fromString('2026-11-14')));
        self::assertSame(14, $birth->yearsUntil(CalendarDate::fromString('2026-11-15')));
        self::assertSame(0, $birth->yearsUntil(CalendarDate::fromString('2012-11-14')));
        self::assertSame(0, $birth->yearsUntil(CalendarDate::fromString('2013-11-13')));
        self::assertSame(1, $birth->yearsUntil(CalendarDate::fromString('2013-11-14')));
        self::assertSame(0, $birth->yearsUntil(CalendarDate::fromString('2012-12-31')));
        self::assertSame(0, $birth->yearsUntil(CalendarDate::fromString('2000-01-01')));
    }

    public function testTheYearsOfABirthdayOn29February(): void
    {
        $leapDay = CalendarDate::fromString('2012-02-29');

        self::assertSame(13, $leapDay->yearsUntil(CalendarDate::fromString('2026-02-28')));
        self::assertSame(14, $leapDay->yearsUntil(CalendarDate::fromString('2026-03-01')));
        self::assertSame(14, $leapDay->yearsUntil(CalendarDate::fromString('2026-02-28'), LeapDayRule::TwentyEighthOfFebruary));
        self::assertSame(13, $leapDay->yearsUntil(CalendarDate::fromString('2026-02-27'), LeapDayRule::TwentyEighthOfFebruary));
        self::assertSame(15, $leapDay->yearsUntil(CalendarDate::fromString('2027-03-01')));
        self::assertSame(15, $leapDay->yearsUntil(CalendarDate::fromString('2028-02-28')));
        self::assertSame(16, $leapDay->yearsUntil(CalendarDate::fromString('2028-02-29')));
    }

    public function testDatesAreComparedByTheirDays(): void
    {
        $a = CalendarDate::fromString('2026-11-13');
        $b = CalendarDate::fromString('2026-11-14');
        $c = CalendarDate::fromString('2026-12-01');
        $d = CalendarDate::fromString('2027-01-01');

        self::assertSame(-1, $a->compare($b));
        self::assertSame(1, $b->compare($a));
        self::assertSame(0, $b->compare(CalendarDate::fromString('2026-11-14')));
        self::assertTrue($a->isBefore($b));
        self::assertFalse($b->isBefore($a));
        self::assertFalse($b->isBefore(CalendarDate::fromString('2026-11-14')));
        self::assertTrue($b->isAfter($a));
        self::assertFalse($a->isAfter($b));
        self::assertFalse($b->isAfter(CalendarDate::fromString('2026-11-14')));
        self::assertTrue($b->isSameDay(CalendarDate::fromString('2026-11-14')));
        self::assertFalse($b->isSameDay($a));
        self::assertTrue($b->isBefore($c));
        self::assertTrue($c->isBefore($d));
        self::assertTrue(CalendarDate::fromString('2026-01-31')->isBefore(CalendarDate::fromString('2026-02-01')));
        self::assertTrue(CalendarDate::fromString('2025-12-31')->isBefore(CalendarDate::fromString('2026-01-01')));
    }
}
