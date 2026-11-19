<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit;

use Phil\Habits\Day;
use Phil\Habits\Exception\InvalidSchedule;
use Phil\Habits\Frequency;
use Phil\Habits\Period;
use Phil\Habits\Schedule;
use Phil\Habits\StreakUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schedule::class)]
#[CoversClass(Period::class)]
#[CoversClass(InvalidSchedule::class)]
final class ScheduleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, Frequency, int, list<int>, StreakUnit}>
     */
    public static function rules(): iterable
    {
        yield 'daily' => ['daily', 'daily', Frequency::Daily, 1, [], StreakUnit::Day];
        yield 'daily in capitals, spaced' => ["  DAILY\t", 'daily', Frequency::Daily, 1, [], StreakUnit::Day];
        yield 'every 3 days' => ['every 3 days', 'every 3 days', Frequency::EveryDays, 3, [], StreakUnit::Interval];
        yield 'every 365 days' => ['Every  365 Days', 'every 365 days', Frequency::EveryDays, 365, [], StreakUnit::Interval];
        yield 'on some days' => ['on fri,mon, wed', 'on mon,wed,fri', Frequency::Weekdays, 3, [1, 3, 5], StreakUnit::Day];
        yield 'on a day twice' => ['on sun,sun', 'on sun', Frequency::Weekdays, 1, [7], StreakUnit::Day];
        yield 'on days spaced only' => ['on tue thu sat', 'on tue,thu,sat', Frequency::Weekdays, 3, [2, 4, 6], StreakUnit::Day];
        yield 'on a trailing comma' => ['on mon,', 'on mon', Frequency::Weekdays, 1, [1], StreakUnit::Day];
        yield 'per week' => ['3 per week', '3 per week', Frequency::PerWeek, 3, [], StreakUnit::Week];
        yield 'seven per week' => ['7 per week', '7 per week', Frequency::PerWeek, 7, [], StreakUnit::Week];
        yield 'per month' => ['10 PER MONTH', '10 per month', Frequency::PerMonth, 10, [], StreakUnit::Month];
        yield 'once a month' => ['1 per month', '1 per month', Frequency::PerMonth, 1, [], StreakUnit::Month];
        yield 'thirty-one per month' => ['31 per month', '31 per month', Frequency::PerMonth, 31, [], StreakUnit::Month];
    }

    /**
     * @param list<int> $weekdays
     */
    #[DataProvider('rules')]
    public function testARuleIsReadAndWrittenBack(string $rule, string $canonical, Frequency $frequency, int $count, array $weekdays, StreakUnit $unit): void
    {
        $schedule = Schedule::parse($rule);

        self::assertSame($frequency, $schedule->frequency);
        self::assertSame($count, $schedule->count);
        self::assertSame($weekdays, $schedule->weekdays);
        self::assertSame($canonical, $schedule->toString());
        self::assertSame($unit, $schedule->streakUnit());
        self::assertEquals($schedule, Schedule::parse($schedule->toString()));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidRules(): iterable
    {
        $grammar = 'A schedule rule is "daily", "every N days", "on mon,wed", "N per week" or "N per month".';
        $number = 'A count in a schedule rule is a whole number from 1 to 999, without leading zeros.';

        yield 'empty' => ['', $grammar];
        yield 'unknown' => ['weekly', $grammar];
        yield 'daily twice' => ['daily daily', $grammar];
        yield 'on alone' => ['on', $grammar];
        yield 'every without days' => ['every 3', $grammar];
        yield 'every in weeks' => ['every 3 weeks', $grammar];
        yield 'per year' => ['3 per year', $grammar];
        yield 'times a week' => ['3 times week', $grammar];
        yield 'four words' => ['3 per week now', $grammar];
        yield 'a zero count' => ['0 per week', $number];
        yield 'a leading zero' => ['03 per week', $number];
        yield 'a negative count' => ['-3 per week', $number];
        yield 'a count of four digits' => ['every 1000 days', $number];
        yield 'a word count' => ['every two days', $number];
        yield 'a new line in a count' => ["every 3\n days", $number];
        yield 'too many a week' => ['8 per week', 'A habit due N times a week takes 1 to 7 times.'];
        yield 'too many a month' => ['32 per month', 'A habit due N times a month takes 1 to 31 times.'];
        yield 'every day' => ['every 1 days', 'A habit due once in every N days takes 2 to 365 days.'];
        yield 'every 366 days' => ['every 366 days', 'A habit due once in every N days takes 2 to 365 days.'];
        yield 'an unknown day' => ['on mon,funday', 'A day of the week is written mon, tue, wed, thu, fri, sat or sun.'];
        yield 'commas only' => ['on ,,', 'A habit due on some days of the week names at least one.'];
        yield 'too long' => [str_repeat(' ', 60).'daily', 'A schedule rule is at most 64 bytes long.'];
    }

    #[DataProvider('invalidRules')]
    public function testARuleOutOfTheGrammarIsRefused(string $rule, string $message): void
    {
        $this->expectExceptionObject(InvalidSchedule::because($message));

        Schedule::parse($rule);
    }

    public function testARuleOf64BytesIsRead(): void
    {
        self::assertSame('daily', Schedule::parse(str_repeat(' ', 59).'daily')->toString());
    }

    /**
     * @return iterable<string, array{list<int>, string}>
     */
    public static function invalidWeekdays(): iterable
    {
        yield 'zero' => [[0], 'A day of the week is numbered from 1 for Monday to 7 for Sunday.'];
        yield 'eight' => [[1, 8], 'A day of the week is numbered from 1 for Monday to 7 for Sunday.'];
        yield 'none' => [[], 'A habit due on some days of the week names at least one.'];
    }

    /**
     * @param list<int> $weekdays
     */
    #[DataProvider('invalidWeekdays')]
    public function testDaysOfTheWeekOutOfBoundsAreRefused(array $weekdays, string $message): void
    {
        $this->expectExceptionObject(InvalidSchedule::because($message));

        Schedule::onWeekdays($weekdays);
    }

    public function testTheBoundsOfTheBuilders(): void
    {
        self::assertSame('every 2 days', Schedule::everyDays(2)->toString());
        self::assertSame('1 per week', Schedule::perWeek(1)->toString());
        self::assertSame('on mon,sun', Schedule::onWeekdays([7, 1])->toString());
    }

    public function testADailyHabitHasOnePeriodPerDay(): void
    {
        $periods = $this->periods(Schedule::daily(), '2026-11-13', '2026-11-15');

        self::assertSame([['2026-11-13', '2026-11-13', 1, 1.0], ['2026-11-14', '2026-11-14', 1, 1.0], ['2026-11-15', '2026-11-15', 1, 1.0]], $periods);
    }

    public function testNoPeriodBeforeTheFirstDay(): void
    {
        self::assertSame([], $this->periods(Schedule::daily(), '2026-11-13', '2026-11-12'));
        self::assertSame([['2026-11-13', '2026-11-13', 1, 1.0]], $this->periods(Schedule::daily(), '2026-11-13', '2026-11-13'));
    }

    public function testWindowsOfNDaysRunFromTheFirstDay(): void
    {
        $periods = $this->periods(Schedule::everyDays(3), '2026-11-13', '2026-11-19');

        self::assertSame([['2026-11-13', '2026-11-15', 1, 3.0], ['2026-11-16', '2026-11-18', 1, 3.0], ['2026-11-19', '2026-11-21', 1, 3.0]], $periods);
    }

    public function testDaysOfTheWeekAreTheOnlyPeriods(): void
    {
        // 2026-11-13 is a Friday.
        $periods = $this->periods(Schedule::onWeekdays([1, 3, 5, 6]), '2026-11-13', '2026-11-18');

        self::assertSame([
            ['2026-11-13', '2026-11-13', 1, 1.75],
            ['2026-11-14', '2026-11-14', 1, 1.75],
            ['2026-11-16', '2026-11-16', 1, 1.75],
            ['2026-11-18', '2026-11-18', 1, 1.75],
        ], $periods);
    }

    public function testWeeksRunFromMondayAndTheFirstIsCut(): void
    {
        // From a Saturday: the first week holds two days, and asks no more than two check-ins.
        $periods = $this->periods(Schedule::perWeek(3), '2026-11-14', '2026-11-23');

        self::assertSame([
            ['2026-11-14', '2026-11-15', 2, 2.0],
            ['2026-11-16', '2026-11-22', 3, 7.0],
            ['2026-11-23', '2026-11-29', 3, 7.0],
        ], $periods);
    }

    public function testMonthsRunFromTheirFirstDayAndTheFirstIsCut(): void
    {
        $periods = $this->periods(Schedule::perMonth(10), '2028-01-25', '2028-03-01');

        self::assertSame([
            ['2028-01-25', '2028-01-31', 7, 7.0],
            ['2028-02-01', '2028-02-29', 10, 29.0],
            ['2028-03-01', '2028-03-31', 10, 31.0],
        ], $periods);
    }

    public function testAPeriodHoldsItsDays(): void
    {
        $period = new Period(Day::fromString('2026-11-16'), Day::fromString('2026-11-22'), 3, 7.0);

        self::assertFalse($period->holds(Day::fromString('2026-11-15')));
        self::assertTrue($period->holds(Day::fromString('2026-11-16')));
        self::assertTrue($period->holds(Day::fromString('2026-11-22')));
        self::assertFalse($period->holds(Day::fromString('2026-11-23')));
    }

    /**
     * @return list<array{string, string, int, float}>
     */
    private function periods(Schedule $schedule, string $first, string $through): array
    {
        $periods = [];

        foreach ($schedule->periods(Day::fromString($first), Day::fromString($through)) as $period) {
            $periods[] = [$period->first->toString(), $period->last->toString(), $period->required, $period->span];
        }

        return $periods;
    }
}
