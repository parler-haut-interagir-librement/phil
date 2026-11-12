<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Astronomy\Phase;
use Phil\Almanac\Astronomy\Season;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\ChineseNewYearRule;
use Phil\Almanac\Rule\DayOfYear;
use Phil\Almanac\Rule\Days;
use Phil\Almanac\Rule\EasterSunday;
use Phil\Almanac\Rule\FixedDate;
use Phil\Almanac\Rule\Month;
use Phil\Almanac\Rule\MoonPhaseRule;
use Phil\Almanac\Rule\NthWeekday;
use Phil\Almanac\Rule\Offset;
use Phil\Almanac\Rule\Relation;
use Phil\Almanac\Rule\RelativeWeekday;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Phil\Almanac\Rule\SeasonRule;
use Phil\Almanac\Rule\WeekdayOnMonthDay;
use Phil\Recurrence\Weekday;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleParser::class)]
#[CoversClass(FixedDate::class)]
#[CoversClass(NthWeekday::class)]
#[CoversClass(RelativeWeekday::class)]
#[CoversClass(Relation::class)]
#[CoversClass(EasterSunday::class)]
#[CoversClass(DayOfYear::class)]
#[CoversClass(WeekdayOnMonthDay::class)]
#[CoversClass(MoonPhaseRule::class)]
#[CoversClass(SeasonRule::class)]
#[CoversClass(ChineseNewYearRule::class)]
#[CoversClass(Offset::class)]
#[CoversClass(Month::class)]
#[CoversClass(Days::class)]
#[CoversClass(InvalidRule::class)]
final class RulesTest extends TestCase
{
    /**
     * Each form read, written back as itself, and its dates in a year.
     *
     * @param class-string<Rule> $class
     * @param list<string>       $dates
     */
    #[DataProvider('rules')]
    public function testARuleIsReadWrittenAndFound(string $text, string $canonical, string $class, int $year, array $dates): void
    {
        $rule = RuleParser::parse($text);

        self::assertInstanceOf($class, $rule);
        self::assertSame($canonical, (string) $rule);
        self::assertSame($canonical, (string) RuleParser::parse($canonical));
        self::assertSame($dates, array_map(static fn (DateTimeImmutable $date): string => $date->format($rule->isInstant() ? 'Y-m-d H:i' : 'Y-m-d'), $rule->in($year, new DateTimeZone('America/Toronto'))));
    }

    /**
     * @return iterable<string, array{string, string, class-string<Rule>, int, list<string>}>
     */
    public static function rules(): iterable
    {
        yield 'a fixed date' => ['12-25', '12-25', FixedDate::class, 2026, ['2026-12-25']];
        yield 'a fixed date in one digit' => ['7-1', '07-01', FixedDate::class, 2026, ['2026-07-01']];
        yield 'February 29 in a leap year' => ['02-29', '02-29', FixedDate::class, 2028, ['2028-02-29']];
        yield 'February 29 in another' => ['02-29', '02-29', FixedDate::class, 2026, []];
        yield 'the 2nd monday of October' => ['2nd Monday of October', '2nd monday of october', NthWeekday::class, 2026, ['2026-10-12']];
        yield 'the first, in words' => ['first  saturday   of may', '1st saturday of may', NthWeekday::class, 2026, ['2026-05-02']];
        yield 'the second, in words' => ['second tuesday of february', '2nd tuesday of february', NthWeekday::class, 2026, ['2026-02-10']];
        yield 'the third, in words' => ['third wednesday of 3', '3rd wednesday of march', NthWeekday::class, 2026, ['2026-03-18']];
        yield 'the fourth, in words' => ['fourth monday of january', '4th monday of january', NthWeekday::class, 2026, ['2026-01-26']];
        yield 'the fifth, in words' => ['fifth sunday of may', '5th sunday of may', NthWeekday::class, 2026, ['2026-05-31']];
        yield 'a fifth weekday a month does not have' => ['5th friday of 2', '5th friday of february', NthWeekday::class, 2026, []];
        yield 'a 1st that is the 1st' => ['1st friday of may', '1st friday of may', NthWeekday::class, 2026, ['2026-05-01']];
        yield 'the last thursday' => ['last thursday of february', 'last thursday of february', NthWeekday::class, 2026, ['2026-02-26']];
        yield 'a last that is the last day' => ['last tuesday of march', 'last tuesday of march', NthWeekday::class, 2026, ['2026-03-31']];
        yield 'the last of December' => ['last saturday of 12', 'last saturday of december', NthWeekday::class, 2026, ['2026-12-26']];
        yield 'a weekday on or after a day' => ['saturday on or after 09-15', 'saturday on or after 09-15', RelativeWeekday::class, 2026, ['2026-09-19']];
        yield 'on or after, the day itself' => ['tuesday on or after 09-15', 'tuesday on or after 09-15', RelativeWeekday::class, 2026, ['2026-09-15']];
        yield 'after, a week later' => ['tuesday after 09-15', 'tuesday after 09-15', RelativeWeekday::class, 2026, ['2026-09-22']];
        yield 'after, the next' => ['friday after 09-15', 'friday after 09-15', RelativeWeekday::class, 2026, ['2026-09-18']];
        yield 'on or before, the day itself' => ['tuesday on or before 09-15', 'tuesday on or before 09-15', RelativeWeekday::class, 2026, ['2026-09-15']];
        yield 'on or before, earlier' => ['monday on or before 05-24', 'monday on or before 05-24', RelativeWeekday::class, 2026, ['2026-05-18']];
        yield 'before, a week earlier' => ['monday before 05-25', 'monday before 05-25', RelativeWeekday::class, 2026, ['2026-05-18']];
        yield 'before, the one before' => ['sunday before 05-25', 'sunday before 05-25', RelativeWeekday::class, 2026, ['2026-05-24']];
        yield 'from February 29 in another year' => ['monday after 02-29', 'monday after 02-29', RelativeWeekday::class, 2026, []];
        yield 'Easter' => ['Easter', 'easter', EasterSunday::class, 2026, ['2026-04-05']];
        yield 'Orthodox Easter' => ['orthodox easter', 'orthodox easter', EasterSunday::class, 2026, ['2026-04-12']];
        yield 'a day of the year' => ['day 256', 'day 256', DayOfYear::class, 2026, ['2026-09-13']];
        yield 'the first day of the year' => ['day 1', 'day 1', DayOfYear::class, 2026, ['2026-01-01']];
        yield 'day 366 of a leap year' => ['day 366', 'day 366', DayOfYear::class, 2028, ['2028-12-31']];
        yield 'day 366 of another' => ['day 366', 'day 366', DayOfYear::class, 2026, []];
        yield 'Friday the 13th' => ['Friday 13', 'friday 13', WeekdayOnMonthDay::class, 2026, ['2026-02-13', '2026-03-13', '2026-11-13']];
        yield 'a 31st on a weekday' => ['saturday 31', 'saturday 31', WeekdayOnMonthDay::class, 2026, ['2026-01-31', '2026-10-31']];
        yield 'a 31st in December' => ['thursday 31', 'thursday 31', WeekdayOnMonthDay::class, 2026, ['2026-12-31']];
        yield 'a 1st' => ['friday 1', 'friday 1', WeekdayOnMonthDay::class, 2026, ['2026-05-01']];
        yield 'spaces around' => ['  Easter  ', 'easter', EasterSunday::class, 2026, ['2026-04-05']];
        yield 'a full moon on the evening of January 1' => ['full moon', 'full moon', MoonPhaseRule::class, 2018, ['2018-01-01 21:24', '2018-01-31 08:26', '2018-03-01 19:51', '2018-03-31 08:36', '2018-04-29 20:58', '2018-05-29 10:19', '2018-06-28 00:53', '2018-07-27 16:20', '2018-08-26 07:56', '2018-09-24 22:52', '2018-10-24 12:45', '2018-11-23 00:39', '2018-12-22 12:48']];
        yield 'none on December 31 of the year before' => ['full moon', 'full moon', MoonPhaseRule::class, 2010, ['2010-01-30 01:17', '2010-02-28 11:37', '2010-03-29 22:25', '2010-04-28 08:18', '2010-05-27 19:07', '2010-06-26 07:30', '2010-07-25 21:36', '2010-08-24 13:04', '2010-09-23 05:17', '2010-10-22 21:36', '2010-11-21 12:27', '2010-12-21 03:13']];
        yield 'a full moon' => ['full moon', 'full moon', MoonPhaseRule::class, 2026, ['2026-01-03 05:02', '2026-02-01 17:09', '2026-03-03 06:37', '2026-04-01 22:11', '2026-05-01 13:23', '2026-05-31 04:45', '2026-06-29 19:56', '2026-07-29 10:35', '2026-08-28 00:18', '2026-09-26 12:48', '2026-10-26 00:11', '2026-11-24 09:53', '2026-12-23 20:28']];
        yield 'a new moon' => ['new moon', 'new moon', MoonPhaseRule::class, 2026, ['2026-01-18 14:52', '2026-02-17 07:01', '2026-03-18 21:23', '2026-04-17 07:51', '2026-05-16 16:00', '2026-06-14 22:54', '2026-07-14 05:43', '2026-08-12 13:36', '2026-09-10 23:26', '2026-10-10 11:49', '2026-11-09 02:01', '2026-12-08 19:51']];
        yield 'a solstice' => ['December Solstice', 'december solstice', SeasonRule::class, 2026, ['2026-12-21 15:50']];
        yield 'an equinox' => ['march equinox', 'march equinox', SeasonRule::class, 2026, ['2026-03-20 10:45']];
        yield 'the Chinese New Year' => ['chinese new year', 'chinese new year', ChineseNewYearRule::class, 2026, ['2026-02-17']];
        yield 'days before' => ['easter - 47 days', 'easter - 47 days', Offset::class, 2026, ['2026-02-17']];
        yield 'one day after' => ['easter+1 day', 'easter + 1 day', Offset::class, 2026, ['2026-04-06']];
        yield 'days after, in the plural' => ['monday on or before 02-22 + 3 days', 'monday on or before 02-22 + 3 days', Offset::class, 2026, ['2026-02-19']];
        yield 'one day before' => ['12-25 - 1 days', '12-25 - 1 day', Offset::class, 2026, ['2026-12-24']];
        yield 'into the next year' => ['12-31 + 1 day', '12-31 + 1 day', Offset::class, 2026, ['2027-01-01']];
        yield 'an instant moved' => ['june solstice + 2 days', 'june solstice + 2 days', Offset::class, 2026, ['2026-06-23 04:24']];
    }

    #[DataProvider('unreadable')]
    public function testARuleIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        RuleParser::parse($text);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'nothing' => ['', '"" is not a rule the almanac reads.'];
        yield 'words' => ['every blue moon', '"every blue moon" is not a rule the almanac reads.'];
        yield 'a long text, cut' => [str_repeat('a', 150), '"'.str_repeat('a', 100).'" is not'];
        yield 'an unknown ordinal' => ['6th monday of may', '"6th monday of may" is not'];
        yield 'an unknown month' => ['1st monday of maybe', '"1st monday of maybe" is not'];
        yield 'month 13' => ['1st monday of 13', 'A month is 1 to 12, not 13.'];
        yield 'month 0' => ['1st monday of 0', 'A month is 1 to 12, not 0.'];
        yield 'an impossible day' => ['02-30', '02-30 is no day of the year.'];
        yield 'a day 0' => ['03-00', '03-00 is no day of the year.'];
        yield 'a month 13 day' => ['13-01', '13-01 is no day of the year.'];
        yield 'a month 0 day' => ['00-10', '00-10 is no day of the year.'];
        yield 'a relative day that is none' => ['monday after 04-31', '04-31 is no day of the year.'];
        yield 'day 0' => ['day 0', 'A day of the year is 1 to 366, not 0.'];
        yield 'day 367' => ['day 367', 'A day of the year is 1 to 366, not 367.'];
        yield 'a month day 0' => ['friday 0', 'A day of the month is 1 to 31, not 0.'];
        yield 'a month day 32' => ['friday 32', 'A day of the month is 1 to 31, not 32.'];
        yield 'no offset' => ['easter + 0 days', 'An offset is 1 to 366 days either way, of a rule that has none; not 0 days of "easter".'];
        yield 'too long an offset' => ['easter - 367 days', 'not -367 days of "easter".'];
        yield 'an offset of an offset' => ['easter + 1 day + 2 days', '"easter + 1 day + 2 days" is not'];
        yield 'an offset of nothing' => ['+ 2 days', '"+ 2 days" is not'];
        yield 'a text around a rule' => ['the easter', '"the easter" is not'];
        yield 'a rule and more' => ['easter sunday', '"easter sunday" is not'];
        yield 'a phase and more' => ['full moon tonight', '"full moon tonight" is not'];
        yield 'a fixed date and more' => ['12-25 and 12-26', '"12-25 and 12-26" is not'];
        yield 'a day of the year and more' => ['day 25 of may', '"day 25 of may" is not'];
        yield 'a three-digit day of the month' => ['friday 130', '"friday 130" is not'];
        yield 'a three-digit date' => ['120-25', '"120-25" is not'];
        yield 'an ordinal on its own' => ['2nd of october', '"2nd of october" is not'];
        yield 'a relation and more' => ['monday on or after 09-15 or so', '"monday on or after 09-15 or so" is not'];
        yield 'an offset in weeks' => ['easter + 2 weeks', '"easter + 2 weeks" is not'];
        yield 'an offset of four digits' => ['easter + 1000 days', '"easter + 1000 days" is not'];
        yield 'a nth weekday and more' => ['1st monday of may 2026', '"1st monday of may 2026" is not'];
        yield 'leading words' => ['every 1st monday of may', '"every 1st monday of may" is not'];
        yield 'a month after letters' => ['1st monday of x5', '"1st monday of x5" is not'];
        yield 'a month before letters' => ['1st monday of 5x', '"1st monday of 5x" is not'];
        yield 'a long text in accents, cut at a character' => [str_repeat('é', 150), '"'.str_repeat('é', 100).'" is not'];
    }

    public function testTheRulesDirectly(): void
    {
        $zone = new DateTimeZone('Europe/Paris');

        self::assertSame(['2026-12-25 00:00 Europe/Paris'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d H:i e'), new FixedDate(12, 25)->in(2026, $zone)));
        self::assertSame(['2026-03-20 15:45 Europe/Paris'], array_map(static fn (DateTimeImmutable $date): string => $date->format('Y-m-d H:i e'), new SeasonRule(Season::MarchEquinox)->in(2026, $zone)));
        self::assertSame('2026-01-03 11:02 Europe/Paris', new MoonPhaseRule(Phase::Full)->in(2026, $zone)[0]->format('Y-m-d H:i e'));
        self::assertSame('2026-02-17 00:00 Europe/Paris', new ChineseNewYearRule()->in(2026, $zone)[0]->format('Y-m-d H:i e'));
        self::assertSame([false, false, false, false, false, false, true, true, false], [
            new FixedDate(1, 1)->isInstant(), new NthWeekday(1, Weekday::Monday, 1)->isInstant(), new RelativeWeekday(Weekday::Monday, Relation::After, 1, 1)->isInstant(),
            new EasterSunday()->isInstant(), new DayOfYear(1)->isInstant(), new WeekdayOnMonthDay(Weekday::Friday, 13)->isInstant(),
            new MoonPhaseRule(Phase::New)->isInstant(), new SeasonRule(Season::JuneSolstice)->isInstant(), new ChineseNewYearRule()->isInstant(),
        ]);
        self::assertTrue(new Offset(new SeasonRule(Season::JuneSolstice), 1)->isInstant());
        self::assertFalse(new Offset(new EasterSunday(), 1)->isInstant());
        self::assertSame([-1, 1], [new NthWeekday(-1, Weekday::Monday, 5)->nth, new NthWeekday(1, Weekday::Monday, 5)->nth]);
        self::assertSame(['on or after', 'after', 'on or before', 'before'], array_map(static fn (Relation $relation): string => $relation->value, Relation::cases()));
        self::assertSame('december', Month::name(12));
    }

    public function testAnOffsetOfAnOffsetIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('An offset is 1 to 366 days either way, of a rule that has none; not 2 days of "easter + 1 day".');

        new Offset(new Offset(new EasterSunday(), 1), 2);
    }

    public function testTheLongestOffsets(): void
    {
        self::assertSame('easter + 366 days', (string) new Offset(new EasterSunday(), 366));
        self::assertSame('easter - 366 days', (string) new Offset(new EasterSunday(), -366));
        self::assertSame('easter - 1 day', (string) new Offset(new EasterSunday(), -1));
    }

    public function testANthWeekdayIsRefused(): void
    {
        foreach ([0, 6, -2] as $nth) {
            try {
                new NthWeekday($nth, Weekday::Monday, 1);
                self::fail((string) $nth);
            } catch (InvalidRule $invalidRule) {
                self::assertSame(\sprintf('A weekday is the 1st to the 5th of its month, or the last; not %d.', $nth), $invalidRule->getMessage());
            }
        }
    }
}
