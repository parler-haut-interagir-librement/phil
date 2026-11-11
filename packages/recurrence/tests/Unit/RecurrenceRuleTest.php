<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\Exception\InvalidRule;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\Weekday;
use Phil\Recurrence\WeekdayNum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceRule::class)]
#[CoversClass(WeekdayNum::class)]
#[CoversClass(Weekday::class)]
#[CoversClass(Frequency::class)]
#[CoversClass(InvalidRule::class)]
final class RecurrenceRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rules(): iterable
    {
        yield 'daily' => ['FREQ=DAILY', 'FREQ=DAILY'];
        yield 'with its prefix and in lowercase' => [' rrule:freq=weekly;interval=2;byday=mo,we ', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE'];
        yield 'every part' => ['WKST=SU;BYSETPOS=-1,1;BYMONTH=12,6;BYMONTHDAY=-1,1;BYDAY=2TU,-1FR,+1MO;COUNT=10;INTERVAL=3;FREQ=YEARLY', 'FREQ=YEARLY;INTERVAL=3;COUNT=10;BYDAY=2TU,-1FR,1MO;BYMONTHDAY=-1,1;BYMONTH=6,12;BYSETPOS=-1,1;WKST=SU'];
        yield 'an interval of one' => ['FREQ=MONTHLY;INTERVAL=1;WKST=MO', 'FREQ=MONTHLY'];
        yield 'an end in UTC' => ['FREQ=DAILY;UNTIL=20261231T235959Z', 'FREQ=DAILY;UNTIL=20261231T235959Z'];
        yield 'an end day' => ['FREQ=DAILY;UNTIL=20261231', 'FREQ=DAILY;UNTIL=20261231'];
        yield 'a floating end, in the time zone given' => ['FREQ=DAILY;UNTIL=20261231T180000', 'FREQ=DAILY;UNTIL=20261231T230000Z'];
        yield 'spaces around parts and values' => [' FREQ = MONTHLY ; BYMONTHDAY = 1, 15 ;wkst=su', 'FREQ=MONTHLY;BYMONTHDAY=1,15;WKST=SU'];
        yield 'repeated values once' => ['FREQ=MONTHLY;BYMONTHDAY=3,3,1', 'FREQ=MONTHLY;BYMONTHDAY=1,3'];
    }

    #[DataProvider('rules')]
    public function testARuleIsReadAndWritten(string $input, string $rule): void
    {
        self::assertSame($rule, (string) RecurrenceRule::parse($input, new DateTimeZone('America/Toronto')));
    }

    public function testARuleHasItsParts(): void
    {
        $rule = RecurrenceRule::parse('FREQ=MONTHLY;INTERVAL=2;BYDAY=-1FR;UNTIL=20261231');

        self::assertSame([Frequency::Monthly, 2, null, '2026-12-31 00:00:00', true, Weekday::Monday], [$rule->frequency, $rule->interval, $rule->count, $rule->until?->format('Y-m-d H:i:s'), $rule->untilIsDate, $rule->weekStart]);
        self::assertSame([Weekday::Friday, -1], [$rule->byDay[0]->weekday, $rule->byDay[0]->ordinal]);
        self::assertSame('UTC', RecurrenceRule::parse('FREQ=DAILY;UNTIL=20261231T235959Z')->until?->getTimezone()->getName());
        self::assertSame(date_default_timezone_get(), RecurrenceRule::parse('FREQ=DAILY;UNTIL=20261231T120000')->until?->getTimezone()->getName());
        self::assertFalse(RecurrenceRule::parse('FREQ=DAILY')->untilIsDate);
        $numbers = RecurrenceRule::parse('FREQ=YEARLY;BYMONTHDAY=15,1;BYMONTH=6;BYSETPOS=-1');
        self::assertSame([[1, 15], [6], [-1]], [$numbers->byMonthDay, $numbers->byMonth, $numbers->bySetPos]);
        self::assertSame([0], array_keys(RecurrenceRule::of(Frequency::Weekly, byDay: ['x' => new WeekdayNum(Weekday::Monday)])->byDay));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'no frequency' => ['INTERVAL=2', '"INTERVAL=2" is not a recurrence rule with a yearly, monthly, weekly or daily frequency.'];
        yield 'an hourly frequency' => ['FREQ=HOURLY', '"FREQ=HOURLY" is not a recurrence rule with a yearly, monthly, weekly or daily frequency.'];
        yield 'a part without value' => ['FREQ=DAILY;COUNT=', '"FREQ=DAILY;COUNT=" is not a recurrence rule.'];
        yield 'a part without name' => ['=DAILY', '"=DAILY" is not a recurrence rule.'];
        yield 'a part with two values' => ['FREQ=DAILY=1', '"FREQ=DAILY=1" is not a recurrence rule with a yearly, monthly, weekly or daily frequency.'];
        yield 'a part of spaces' => ['FREQ=DAILY;COUNT= ;INTERVAL=2', '"FREQ=DAILY;COUNT= ;INTERVAL=2" is not a recurrence rule.'];
        yield 'a weekday after something' => ['FREQ=WEEKLY;BYDAY=XMO', '"XMO" is not a weekday of a rule.'];
        yield 'a part twice' => ['FREQ=DAILY;FREQ=WEEKLY', '"FREQ=DAILY;FREQ=WEEKLY" is not a recurrence rule.'];
        yield 'an empty rule' => ['', '"" is not a recurrence rule.'];
        yield 'a part not read' => ['FREQ=DAILY;BYHOUR=9;BYWEEKNO=2', 'The part BYHOUR, BYWEEKNO of a recurrence rule is not read.'];
        yield 'a count that is no number' => ['FREQ=DAILY;COUNT=ten', '"ten" is not a number of a rule.'];
        yield 'a number too long' => ['FREQ=DAILY;COUNT=12345', '"12345" is not a number of a rule.'];
        yield 'a weekday that is none' => ['FREQ=WEEKLY;BYDAY=XX', '"XX" is not a weekday of a rule.'];
        yield 'a weekday after its day' => ['FREQ=MONTHLY;BYDAY=MO1', '"MO1" is not a weekday of a rule.'];
        yield 'a week start that is none' => ['FREQ=WEEKLY;WKST=XX', '"XX" is not a weekday.'];
        yield 'an end that is no day' => ['FREQ=DAILY;UNTIL=2026-12-31', '"2026-12-31" is not an end of a rule: a day, or a time.'];
        yield 'an end that does not exist' => ['FREQ=DAILY;UNTIL=20260230', '"20260230" is not an end of a rule: a day, or a time.'];
    }

    #[DataProvider('unreadable')]
    public function testWhatIsNoRuleIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RecurrenceRule::parse($input);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbidden(): iterable
    {
        yield 'an interval of zero' => ['FREQ=DAILY;INTERVAL=0', 'The interval of a rule is 1 to 9999.'];
        yield 'a count of zero' => ['FREQ=DAILY;COUNT=0', 'The count of a rule is 1 to 9999.'];
        yield 'a count and an end' => ['FREQ=DAILY;COUNT=3;UNTIL=20261231', 'A rule ends after a count or on a date, not both.'];
        yield 'a place in a weekly rule' => ['FREQ=WEEKLY;BYDAY=1MO', 'A weekday has a place in its month or year in monthly and yearly rules only.'];
        yield 'a place in a daily rule' => ['FREQ=DAILY;BYDAY=-1MO', 'A weekday has a place in its month or year in monthly and yearly rules only.'];
        yield 'a sixth weekday of a month' => ['FREQ=MONTHLY;BYDAY=6MO', 'The place of a weekday in its month is -5 to 5.'];
        yield 'a sixth weekday from the end of a month' => ['FREQ=MONTHLY;BYDAY=-6MO', 'The place of a weekday in its month is -5 to 5.'];
        yield 'a weekday beyond a year' => ['FREQ=YEARLY;BYDAY=54MO', 'The place of a weekday in its month or year is -53 to 53 and not 0; 54 is not.'];
        yield 'a weekday at place zero' => ['FREQ=YEARLY;BYDAY=0MO', 'The place of a weekday in its month or year is -53 to 53 and not 0; 0 is not.'];
        yield 'a month day in a weekly rule' => ['FREQ=WEEKLY;BYMONTHDAY=1', 'A weekly rule has no month days.'];
        yield 'a month day of zero' => ['FREQ=MONTHLY;BYMONTHDAY=0', 'A month day of a rule is -31 to 31 and not 0.'];
        yield 'a 32nd' => ['FREQ=MONTHLY;BYMONTHDAY=32', 'A month day of a rule is -31 to 31 and not 0.'];
        yield 'a 32nd from the end' => ['FREQ=MONTHLY;BYMONTHDAY=-32', 'A month day of a rule is -31 to 31 and not 0.'];
        yield 'a month zero' => ['FREQ=YEARLY;BYMONTH=0', 'A month of a rule is 1 to 12.'];
        yield 'a thirteenth month' => ['FREQ=YEARLY;BYMONTH=13', 'A month of a rule is 1 to 12.'];
        yield 'a set position of zero' => ['FREQ=MONTHLY;BYDAY=MO;BYSETPOS=0', 'A set position of a rule is -366 to 366 and not 0.'];
        yield 'a set position too far' => ['FREQ=MONTHLY;BYDAY=MO;BYSETPOS=367', 'A set position of a rule is -366 to 366 and not 0.'];
        yield 'a set position too far from the end' => ['FREQ=MONTHLY;BYDAY=MO;BYSETPOS=-367', 'A set position of a rule is -366 to 366 and not 0.'];
        yield 'a set position alone' => ['FREQ=MONTHLY;BYSETPOS=1', 'A set position needs another BY part.'];
    }

    #[DataProvider('forbidden')]
    public function testWhatRfc5545ForbidsIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RecurrenceRule::parse($input);
    }

    public function testAnEndDayNeedsItsEnd(): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('A rule ends on a day only with its end.');

        RecurrenceRule::of(Frequency::Daily, untilIsDate: true);
    }

    /**
     * @return iterable<string, array{array{interval?: int, count?: int, until?: DateTimeImmutable, untilIsDate?: bool}, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'an interval of 10000' => [['interval' => 10_000], 'The interval of a rule is 1 to 9999.'];
        yield 'an interval of zero' => [['interval' => 0], 'The interval of a rule is 1 to 9999.'];
        yield 'a count of 10000' => [['count' => 10_000], 'The count of a rule is 1 to 9999.'];
        yield 'a count of zero' => [['count' => 0], 'The count of a rule is 1 to 9999.'];
        yield 'an end day after the year 9999' => [['until' => new DateTimeImmutable('2026-01-01')->setDate(10_000, 1, 1), 'untilIsDate' => true], 'The end of a rule is in the years 1 to 9999.'];
        yield 'an end day in the year 0' => [['until' => new DateTimeImmutable('0000-12-31'), 'untilIsDate' => true], 'The end of a rule is in the years 1 to 9999.'];
        yield 'an end in the year 10000 once in UTC' => [['until' => new DateTimeImmutable('9999-12-31 23:00', new DateTimeZone('America/Toronto'))], 'The end of a rule is in the years 1 to 9999.'];
        yield 'an end in the year 0 once in UTC' => [['until' => new DateTimeImmutable('0001-01-01 00:30', new DateTimeZone('+01:00'))], 'The end of a rule is in the years 1 to 9999.'];
    }

    /**
     * @param array{interval?: int, count?: int, until?: DateTimeImmutable, untilIsDate?: bool} $arguments
     */
    #[DataProvider('outOfBounds')]
    public function testWhatCannotBeWrittenAndReadBackIsRefused(array $arguments, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RecurrenceRule::of(Frequency::Daily, ...$arguments);
    }

    /**
     * Every rule `of()` builds is written as `parse()` reads it back: a rule stored as text stays readable.
     */
    public function testARuleAtTheBoundsIsReadBack(): void
    {
        $rules = [
            RecurrenceRule::of(Frequency::Daily, interval: 9999),
            RecurrenceRule::of(Frequency::Weekly, count: 9999),
            RecurrenceRule::of(Frequency::Daily, until: new DateTimeImmutable('9999-12-31 23:59:59', new DateTimeZone('UTC'))),
            RecurrenceRule::of(Frequency::Daily, until: new DateTimeImmutable('0001-01-01 00:00', new DateTimeZone('UTC'))),
            RecurrenceRule::of(Frequency::Daily, until: new DateTimeImmutable('9999-12-31 23:00', new DateTimeZone('America/Toronto')), untilIsDate: true),
            RecurrenceRule::of(Frequency::Daily, until: new DateTimeImmutable('0001-01-01 00:30', new DateTimeZone('+01:00')), untilIsDate: true),
        ];

        foreach ($rules as $rule) {
            self::assertSame((string) $rule, (string) RecurrenceRule::parse((string) $rule));
        }

        self::assertSame(['FREQ=DAILY;INTERVAL=9999', 'FREQ=WEEKLY;COUNT=9999', 'FREQ=DAILY;UNTIL=99991231T235959Z', 'FREQ=DAILY;UNTIL=00010101T000000Z', 'FREQ=DAILY;UNTIL=99991231', 'FREQ=DAILY;UNTIL=00010101'], array_map(strval(...), $rules));
    }

    public function testTheLimitsAreAllowed(): void
    {
        self::assertSame('FREQ=YEARLY;BYDAY=53MO,-53TU;BYMONTHDAY=-31,31;BYMONTH=1,12;BYSETPOS=-366,366', (string) RecurrenceRule::parse('FREQ=YEARLY;BYDAY=53MO,-53TU;BYMONTHDAY=31,-31;BYMONTH=1,12;BYSETPOS=366,-366'));
        self::assertSame('FREQ=MONTHLY;BYDAY=5MO,-5TU', (string) RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=5MO,-5TU'));
        self::assertSame('FREQ=MONTHLY;BYDAY=MO;BYSETPOS=1', (string) RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=MO;BYSETPOS=1'));
        self::assertSame('FREQ=MONTHLY;BYMONTH=6;BYSETPOS=1', (string) RecurrenceRule::parse('FREQ=MONTHLY;BYMONTH=6;BYSETPOS=1'));
        self::assertSame('FREQ=MONTHLY;BYMONTHDAY=6;BYSETPOS=1', (string) RecurrenceRule::parse('FREQ=MONTHLY;BYMONTHDAY=6;BYSETPOS=1'));
        self::assertSame('FREQ=DAILY;COUNT=1', (string) RecurrenceRule::of(Frequency::Daily, count: 1));
        self::assertSame('FREQ=DAILY;UNTIL=20261231T050000Z', (string) RecurrenceRule::of(Frequency::Daily, until: new DateTimeImmutable('2026-12-31 00:00', new DateTimeZone('America/Toronto'))));
    }

    public function testWeekdays(): void
    {
        self::assertSame([Weekday::cases(), [1, 2, 3, 4, 5, 6, 7]], [array_map(static fn (int $day): Weekday => Weekday::of(new DateTimeImmutable(\sprintf('2026-11-%02d', $day))), range(2, 8)), array_map(static fn (Weekday $day): int => $day->iso(), Weekday::cases())]);
        self::assertSame(['MO', '-1FR', '2TU'], [(string) WeekdayNum::parse('mo'), (string) WeekdayNum::parse('-1fr'), (string) WeekdayNum::parse(' +2TU ')]);

        try {
            WeekdayNum::parse(str_repeat('é', 150));
            self::fail('A long weekday was read.');
        } catch (InvalidRule $invalidRule) {
            self::assertSame('"'.str_repeat('é', 100).'" is not a weekday of a rule.', $invalidRule->getMessage());
        }
    }
}
