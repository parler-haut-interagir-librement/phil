<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidDay;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Day::class)]
#[CoversClass(InvalidDay::class)]
final class DayTest extends TestCase
{
    public function testADayIsNumberedFromTheEpoch(): void
    {
        self::assertSame(0, Day::fromString('1970-01-01')->number);
        self::assertSame(-1, Day::fromString('1969-12-31')->number);
        self::assertSame(20_405, Day::fromString('2025-11-13')->number);
        self::assertSame('1969-12-31', Day::fromNumber(-1)->toString());
    }

    public function testTheCalendarRunsFromYearOneToYear9999(): void
    {
        self::assertSame('0001-01-01', Day::fromString('0001-01-01')->toString());
        self::assertSame(-719_162, Day::fromString('0001-01-01')->number);
        self::assertSame(2_932_896, Day::fromString('9999-12-31')->number);
        self::assertSame('9999-12-31', Day::fromNumber(2_932_896)->toString());
        self::assertSame('0001-01-01', Day::fromNumber(-719_162)->toString());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfRange(): iterable
    {
        yield 'before year one' => [-719_163];
        yield 'after year 9999' => [2_932_897];
    }

    #[DataProvider('outOfRange')]
    public function testANumberOutOfTheCalendarIsRefused(int $number): void
    {
        $this->expectExceptionObject(InvalidDay::because('A day runs from 0001-01-01 to 9999-12-31.'));

        Day::fromNumber($number);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDays(): iterable
    {
        $format = 'A day is written as YYYY-MM-DD.';
        $calendar = 'The day is not a day of the calendar.';

        yield 'empty' => ['', $format];
        yield 'no dashes' => ['20261113', $format];
        yield 'a time' => ['2026-11-13 10:00', $format];
        yield 'a new line after' => ["2026-11-13\n", $format];
        yield 'a text before' => ['x2026-11-13', $format];
        yield 'a short year' => ['226-11-13', $format];
        yield 'year zero' => ['0000-01-01', $calendar];
        yield 'month 13' => ['2026-13-01', $calendar];
        yield 'February 29 of a common year' => ['2026-02-29', $calendar];
        yield 'day 0' => ['2026-01-00', $calendar];
    }

    #[DataProvider('invalidDays')]
    public function testADayNotWrittenOrNotInTheCalendarIsRefused(string $day, string $message): void
    {
        $this->expectExceptionObject(InvalidDay::because($message));

        Day::fromString($day);
    }

    public function testFebruary29OfALeapYearIsADay(): void
    {
        self::assertSame('2028-02-29', Day::fromString('2028-02-29')->toString());
    }

    public function testAnInstantFallsOnTheDayOfItsZone(): void
    {
        $instant = new DateTimeImmutable('2026-11-13 03:30:00', new DateTimeZone('UTC'));

        self::assertSame('2026-11-13', Day::of($instant, new DateTimeZone('UTC'))->toString());
        self::assertSame('2026-11-12', Day::of($instant, new DateTimeZone('America/Toronto'))->toString());
        self::assertSame('2026-11-13', Day::of($instant, new DateTimeZone('Asia/Tokyo'))->toString());
        self::assertSame('03:30', $instant->format('H:i'));
    }

    public function testDaysAddAndCompare(): void
    {
        $day = Day::fromString('2026-12-30');
        $later = $day->plus(3);

        self::assertSame('2027-01-02', $later->toString());
        self::assertSame('2026-12-29', $day->plus(-1)->toString());
        self::assertSame(3, $day->daysUntil($later));
        self::assertSame(-3, $later->daysUntil($day));
        self::assertTrue($day->isBefore($later));
        self::assertFalse($later->isBefore($day));
        self::assertFalse($day->isBefore($day));
        self::assertTrue($later->isAfter($day));
        self::assertFalse($day->isAfter($later));
        self::assertFalse($day->isAfter($day));
        self::assertTrue($day->isSameAs(Day::fromString('2026-12-30')));
        self::assertFalse($day->isSameAs($later));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function weeks(): iterable
    {
        yield 'a Monday' => ['2026-11-09', 1, '2026-11-09'];
        yield 'a Thursday' => ['1970-01-01', 4, '1969-12-29'];
        yield 'a Sunday' => ['2026-11-15', 7, '2026-11-09'];
        yield 'a Wednesday before the epoch' => ['1969-12-31', 3, '1969-12-29'];
        yield 'a Sunday before the epoch' => ['1969-12-28', 7, '1969-12-22'];
        yield 'a Saturday across years' => ['2027-01-02', 6, '2026-12-28'];
    }

    #[DataProvider('weeks')]
    public function testTheWeekRunsFromMondayToSunday(string $day, int $weekday, string $monday): void
    {
        self::assertSame($weekday, Day::fromString($day)->weekday());
        self::assertSame($monday, Day::fromString($day)->weekStart()->toString());
    }

    public function testTheMonthOfADay(): void
    {
        $day = Day::fromString('2028-02-17');

        self::assertSame(17, $day->dayOfMonth());
        self::assertSame(29, $day->daysInMonth());
        self::assertSame('2028-02-01', $day->monthStart()->toString());
        self::assertSame('2028-02-29', $day->monthEnd()->toString());
        self::assertSame('2026-12-31', Day::fromString('2026-12-01')->monthEnd()->toString());
        self::assertSame(30, Day::fromString('2026-11-30')->daysInMonth());
    }
}
