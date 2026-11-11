<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Recurrence::class)]
final class RecurrenceTest extends TestCase
{
    private static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('America/Toronto'));
    }

    /**
     * The examples of RFC 5545, section 3.8.5.3, and their like.
     *
     * @return iterable<string, array{string, string, list<string>}>
     */
    public static function rules(): iterable
    {
        yield 'daily for 10 occurrences' => ['1997-09-02 09:00', 'FREQ=DAILY;COUNT=10', ['09-02', '09-03', '09-04', '09-05', '09-06', '09-07', '09-08', '09-09', '09-10', '09-11']];
        yield 'every other day' => ['1997-09-02 09:00', 'FREQ=DAILY;INTERVAL=2;COUNT=4', ['09-02', '09-04', '09-06', '09-08']];
        yield 'daily in January' => ['1998-01-01 09:00', 'FREQ=DAILY;BYMONTH=1;COUNT=3', ['01-01', '01-02', '01-03']];
        yield 'daily on some days and month days' => ['1997-09-02 09:00', 'FREQ=DAILY;BYDAY=FR;BYMONTHDAY=13;COUNT=3', ['09-02', '02-13', '03-13']];
        yield 'daily on the last day' => ['1997-09-02 09:00', 'FREQ=DAILY;BYMONTHDAY=-1;COUNT=3', ['09-02', '09-30', '10-31']];
        yield 'daily until a day' => ['1997-09-02 09:00', 'FREQ=DAILY;UNTIL=19970905', ['09-02', '09-03', '09-04', '09-05']];
        yield 'weekly' => ['1997-09-02 09:00', 'FREQ=WEEKLY;COUNT=3', ['09-02', '09-09', '09-16']];
        yield 'every other week on Tuesday and Thursday' => ['1997-09-02 09:00', 'FREQ=WEEKLY;INTERVAL=2;WKST=SU;BYDAY=TU,TH;COUNT=8', ['09-02', '09-04', '09-16', '09-18', '09-30', '10-02', '10-14', '10-16']];
        yield 'every other week from Sunday' => ['1997-08-05 09:00', 'FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=SU', ['08-05', '08-17', '08-19', '08-31']];
        yield 'every other week from Monday' => ['1997-08-05 09:00', 'FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=MO', ['08-05', '08-10', '08-19', '08-24']];
        yield 'weekly in June only' => ['1997-06-03 09:00', 'FREQ=WEEKLY;BYMONTH=6;COUNT=3', ['06-03', '06-10', '06-17']];
        yield 'monthly on the first Friday' => ['1997-09-05 09:00', 'FREQ=MONTHLY;BYDAY=1FR;COUNT=5', ['09-05', '10-03', '11-07', '12-05', '01-02']];
        yield 'monthly on the first and last Sunday' => ['1997-09-07 09:00', 'FREQ=MONTHLY;INTERVAL=2;COUNT=6;BYDAY=1SU,-1SU', ['09-07', '09-28', '11-02', '11-30', '01-04', '01-25']];
        yield 'monthly on the second last Monday' => ['1997-09-22 09:00', 'FREQ=MONTHLY;COUNT=4;BYDAY=-2MO', ['09-22', '10-20', '11-17', '12-22']];
        yield 'monthly on the third last day' => ['1997-09-28 09:00', 'FREQ=MONTHLY;BYMONTHDAY=-3;COUNT=3', ['09-28', '10-29', '11-28']];
        yield 'monthly on the 2nd and 15th' => ['1997-09-02 09:00', 'FREQ=MONTHLY;COUNT=5;BYMONTHDAY=2,15', ['09-02', '09-15', '10-02', '10-15', '11-02']];
        yield 'monthly on the 31st, skipping shorter months' => ['1997-08-31 09:00', 'FREQ=MONTHLY;COUNT=4', ['08-31', '10-31', '12-31', '01-31']];
        yield 'monthly on Friday the 13th' => ['1997-09-02 09:00', 'FREQ=MONTHLY;BYDAY=FR;BYMONTHDAY=13;COUNT=4', ['09-02', '02-13', '03-13', '11-13']];
        yield 'monthly on the last weekday' => ['1997-09-29 09:00', 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;COUNT=5', ['09-29', '09-30', '10-31', '11-28', '12-31']];
        yield 'monthly on the third Tuesday, Wednesday or Thursday' => ['1997-09-04 09:00', 'FREQ=MONTHLY;COUNT=3;BYDAY=TU,WE,TH;BYSETPOS=3', ['09-04', '10-07', '11-06']];
        yield 'monthly in some months' => ['1997-09-10 09:00', 'FREQ=MONTHLY;BYMONTH=1,9;COUNT=3', ['09-10', '01-10', '09-10']];
        yield 'yearly in June and July' => ['1997-06-10 09:00', 'FREQ=YEARLY;COUNT=6;BYMONTH=6,7', ['06-10', '07-10', '06-10', '07-10', '06-10', '07-10']];
        yield 'yearly on the 20th Monday' => ['1997-05-19 09:00', 'FREQ=YEARLY;BYDAY=20MO;COUNT=3', ['05-19', '05-18', '05-17']];
        yield 'yearly on every Thursday in March' => ['1997-03-13 09:00', 'FREQ=YEARLY;BYMONTH=3;BYDAY=TH;COUNT=4', ['03-13', '03-20', '03-27', '03-05']];
        yield 'yearly on the last Sunday of the year' => ['1997-12-28 09:00', 'FREQ=YEARLY;BYDAY=-1SU;COUNT=3', ['12-28', '12-27', '12-26']];
        yield 'yearly on February 29' => ['2024-02-29 09:00', 'FREQ=YEARLY;COUNT=3', ['02-29', '02-29', '02-29']];
        yield 'yearly on the first of each month' => ['1997-01-01 09:00', 'FREQ=YEARLY;BYMONTHDAY=1;COUNT=3', ['01-01', '02-01', '03-01']];
        yield 'yearly on the first Monday of each month' => ['1997-01-06 09:00', 'FREQ=YEARLY;BYMONTH=1,2;BYDAY=1MO;COUNT=3', ['01-06', '02-03', '01-05']];
        yield 'yearly, every fourth year, on the election' => ['1996-11-05 09:00', 'FREQ=YEARLY;INTERVAL=4;BYMONTH=11;BYDAY=TU;BYMONTHDAY=2,3,4,5,6,7,8;COUNT=3', ['11-05', '11-07', '11-02']];
        yield 'yearly on the last day of the year by set position' => ['1997-12-31 09:00', 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=31,30;BYSETPOS=-1;COUNT=2', ['12-31', '12-31']];
        yield 'monthly on a set position of month days' => ['1997-09-01 09:00', 'FREQ=MONTHLY;BYMONTHDAY=1,15,28;BYSETPOS=2;COUNT=3', ['09-01', '09-15', '10-15']];
        yield 'monthly on a set position of weekdays and month days' => ['1997-09-01 09:00', 'FREQ=MONTHLY;BYDAY=MO;BYMONTHDAY=1,2,3,4,5,6,7,8,9,10,11,12,13,14;BYSETPOS=2;COUNT=3', ['09-01', '09-08', '10-13']];
        yield 'yearly on the first Monday, Jan 1 a Monday' => ['2028-01-03 09:00', 'FREQ=YEARLY;BYDAY=1MO;COUNT=3', ['01-03', '01-01', '01-07']];
        yield 'monthly on the first Friday, the last day before a Friday' => ['2026-05-01 09:00', 'FREQ=MONTHLY;BYDAY=1FR;COUNT=3', ['05-01', '06-05', '07-03']];
        yield 'every other week from its start day' => ['2026-11-02 09:00', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=3', ['11-02', '11-04', '11-16']];
        yield 'yearly on the first of each month, through the year' => ['1997-01-01 09:00', 'FREQ=YEARLY;BYMONTHDAY=1;COUNT=14', ['01-01', '02-01', '03-01', '04-01', '05-01', '06-01', '07-01', '08-01', '09-01', '10-01', '11-01', '12-01', '01-01', '02-01']];
        yield 'weekly on a set position' => ['2026-11-02 09:00', 'FREQ=WEEKLY;BYDAY=MO,WE,FR;BYSETPOS=-1;COUNT=3', ['11-02', '11-06', '11-13']];
        yield 'daily on a set position of weekdays' => ['2026-11-02 09:00', 'FREQ=DAILY;BYDAY=MO,TU;BYSETPOS=1;COUNT=3', ['11-02', '11-03', '11-09']];
        yield 'yearly on the last Monday, a December 31' => ['2028-12-25 09:00', 'FREQ=YEARLY;BYDAY=-1MO;COUNT=2', ['12-25', '12-31']];
        yield 'yearly on the last Monday, before a Monday January 1' => ['2027-12-27 09:00', 'FREQ=YEARLY;BYDAY=-1MO;COUNT=2', ['12-27', '12-25']];
        yield 'yearly on the last Sunday of a leap year' => ['2027-12-26 09:00', 'FREQ=YEARLY;BYDAY=-1SU;COUNT=2', ['12-26', '12-31']];
        yield 'yearly on two weekdays' => ['2026-01-05 09:00', 'FREQ=YEARLY;BYDAY=TU,MO;COUNT=4', ['01-05', '01-06', '01-12', '01-13']];
        yield 'yearly on a weekday given twice' => ['2026-01-05 09:00', 'FREQ=YEARLY;BYDAY=1MO,MO;COUNT=3', ['01-05', '01-12', '01-19']];
        yield 'yearly in June by month' => ['2026-06-10 09:00', 'FREQ=YEARLY;BYMONTH=6;BYMONTHDAY=10;COUNT=2', ['06-10', '06-10']];
        yield 'monthly on the first and the last Monday by position' => ['2026-11-02 09:00', 'FREQ=MONTHLY;BYDAY=MO;BYSETPOS=-1,1;COUNT=4', ['11-02', '11-30', '12-07', '12-28']];
        yield 'monthly on one day by two positions' => ['2026-11-15 09:00', 'FREQ=MONTHLY;BYMONTHDAY=15;BYSETPOS=1,-1;COUNT=3', ['11-15', '12-15', '01-15']];
        yield 'monthly on the last day of February' => ['2026-01-31 09:00', 'FREQ=MONTHLY;BYMONTHDAY=-1,-29;COUNT=4', ['01-31', '02-28', '03-03', '03-31']];
        yield 'yearly on the last Monday, before a Monday January 1 after a common year' => ['2016-12-26 09:00', 'FREQ=YEARLY;BYDAY=-1MO;COUNT=2', ['12-26', '12-25']];
        yield 'yearly on a weekday given twice, a year later' => ['2026-12-28 09:00', 'FREQ=YEARLY;BYDAY=1MO,MO;COUNT=3', ['12-28', '01-04', '01-11']];
        yield 'no day matches' => ['1997-01-01 09:00', 'FREQ=MONTHLY;BYMONTHDAY=30;BYMONTH=2', ['01-01']];
        yield 'monthly on the first and the last day' => ['2026-01-01 09:00', 'FREQ=MONTHLY;BYMONTHDAY=1,-1;COUNT=4', ['01-01', '01-31', '02-01', '02-28']];
        yield 'monthly on one day given twice' => ['2026-03-01 09:00', 'FREQ=MONTHLY;BYMONTHDAY=30,-1;COUNT=5', ['03-01', '03-30', '03-31', '04-30', '05-30']];
        yield 'monthly on the 31st by month day, skipping shorter months' => ['2026-03-31 09:00', 'FREQ=MONTHLY;BYMONTHDAY=31;COUNT=3', ['03-31', '05-31', '07-31']];
        yield 'monthly on the 31st day from the end, skipping shorter months' => ['2026-01-01 09:00', 'FREQ=MONTHLY;BYMONTHDAY=-31;COUNT=3', ['01-01', '03-01', '05-01']];
        yield 'yearly on March 1 of a leap year' => ['2028-01-15 09:00', 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;COUNT=2', ['01-15', '03-01']];
        yield 'yearly in December of a leap year' => ['2028-01-15 09:00', 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=31;COUNT=2', ['01-15', '12-31']];
    }

    /**
     * @param list<string> $days
     */
    #[DataProvider('rules')]
    public function testARuleRepeatsAsRfc5545Says(string $start, string $rule, array $days): void
    {
        $occurrences = Recurrence::of(self::at($start), RecurrenceRule::parse($rule))->between(limit: 20);

        self::assertSame($days, array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $occurrences));
        self::assertSame(array_fill(0, \count($days), '09:00'), array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('H:i'), $occurrences));
    }

    public function testOccurrencesKeepTheWallClockAcrossChangesOfTime(): void
    {
        $occurrences = Recurrence::of(self::at('2026-03-01 02:30'), RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'))->between();

        self::assertSame(['2026-03-01T02:30:00-05:00', '2026-03-08T03:30:00-04:00', '2026-03-15T02:30:00-04:00'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('c'), $occurrences));
        self::assertSame('2026-11-01T09:00:00-05:00', Recurrence::of(self::at('2026-10-25 09:00'), RecurrenceRule::parse('FREQ=WEEKLY'))->next(self::at('2026-10-25 09:00'))?->format('c'));
        self::assertSame('2026-11-03 09:00:00.250000', Recurrence::of(self::at('2026-11-02 09:00:00.25'), RecurrenceRule::parse('FREQ=DAILY'))->next(self::at('2026-11-02 10:00'))?->format('Y-m-d H:i:s.u'));
    }

    public function testExclusionsAreTakenAwayAndStillCount(): void
    {
        $recurrence = Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=DAILY;COUNT=4'), [new DateTimeImmutable('2026-11-03 14:00 UTC'), self::at('2026-11-02 09:00')]);

        self::assertSame(['11-04', '11-05'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $recurrence->between()));
        self::assertCount(2, $recurrence->exclusions);
        self::assertSame(['11-03', '11-04'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=DAILY;COUNT=3'), [new DateTime('2026-11-02 09:00', new DateTimeZone('America/Toronto'))])->between()));
    }

    public function testAddedDatesAreMergedInOrderAndCountedOutsideTheCount(): void
    {
        $recurrence = Recurrence::of(
            self::at('2026-11-02 09:00'),
            RecurrenceRule::parse('FREQ=WEEKLY;COUNT=3'),
            [self::at('2026-11-09 09:00')],
            [new DateTimeImmutable('2026-11-20 17:00 UTC'), self::at('2026-11-03 18:30'), self::at('2026-11-16 09:00'), self::at('2026-11-02 09:00'), self::at('2026-11-03 18:30'), self::at('2026-10-30 10:00')],
        );

        self::assertSame(
            ['10-30 10:00', '11-02 09:00', '11-03 18:30', '11-16 09:00', '11-20 12:00'],
            array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d H:i'), $recurrence->between()),
            "an excluded occurrence still counts; an added date the rule gives comes once; added dates follow the end of the count, in the start's zone",
        );
        self::assertSame('America/Toronto', $recurrence->between()[4]->getTimezone()->getName());
        self::assertCount(6, $recurrence->additions);
        self::assertSame(
            ['10-30', '11-02', '11-03', '11-16', '11-20'],
            array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), iterator_to_array($recurrence->occurrences(), false)),
        );
    }

    public function testAnAddedDateIsTakenAwayByAnExclusionAndIgnoresTheEnd(): void
    {
        $recurrence = Recurrence::of(
            self::at('2026-11-02 09:00'),
            RecurrenceRule::parse('FREQ=DAILY;UNTIL=20261103', new DateTimeZone('America/Toronto')),
            [self::at('2026-11-10 09:00')],
            [self::at('2026-11-10 09:00'), self::at('2026-12-25 09:00')],
        );

        self::assertSame(['11-02', '11-03', '12-25'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $recurrence->between()));
    }

    public function testAddedDatesAreFoundInWindowsAndByNext(): void
    {
        $recurrence = Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=MONTHLY'), [], [self::at('2026-11-20 19:00'), self::at('2027-01-15 19:00')]);
        $days = static fn (array $occurrences): array => array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d H:i'), $occurrences);

        self::assertSame(['2026-11-20 19:00', '2026-12-02 09:00'], $days($recurrence->between(self::at('2026-11-03'), self::at('2026-12-31'))));
        self::assertSame(['2026-12-02 09:00', '2027-01-02 09:00', '2027-01-15 19:00'], $days($recurrence->between(self::at('2026-11-20 19:00:01'), self::at('2027-01-15 19:00'))));
        self::assertSame(['2026-11-20 19:00'], $days($recurrence->between(self::at('2026-11-20 19:00'), limit: 1)));
        self::assertSame('2026-11-20 19:00', $recurrence->next(self::at('2026-11-02 09:00'))?->format('Y-m-d H:i'));
        self::assertSame('2027-01-15 19:00', $recurrence->next(self::at('2027-01-02 09:00'))?->format('Y-m-d H:i'));
        self::assertSame('2027-01-15 19:00', Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=MONTHLY;COUNT=2'), [], [self::at('2027-01-15 19:00')])->next(self::at('2026-12-02 09:00'))?->format('Y-m-d H:i'));
        self::assertNull(Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=MONTHLY;COUNT=2'), [], [self::at('2026-11-20 19:00')])->next(self::at('2026-12-02 09:00')));
    }

    public function testAnAddedDateBetweenExcludedOccurrencesKeepsItsPlace(): void
    {
        $recurrence = Recurrence::of(
            self::at('2026-11-02 09:00'),
            RecurrenceRule::parse('FREQ=DAILY;COUNT=5'),
            [self::at('2026-11-03 09:00'), self::at('2026-11-04 09:00')],
            [self::at('2026-11-03 12:00'), self::at('2026-11-05 09:00:00.5')],
        );

        self::assertSame(
            ['11-02 09:00:00.0', '11-03 12:00:00.0', '11-05 09:00:00.0', '11-05 09:00:00.5', '11-06 09:00:00.0'],
            array_map(static fn (DateTimeImmutable $occurrence): string => substr($occurrence->format('m-d H:i:s.u'), 0, 16), $recurrence->between()),
        );
    }

    public function testAThousandOccurrencesAtMostByDefault(): void
    {
        $recurrence = Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=DAILY;COUNT=1001'));

        self::assertCount(1000, $recurrence->between());
        self::assertCount(1001, iterator_to_array($recurrence->occurrences(), false));
        self::assertCount(1001, $recurrence->between(limit: 2000));
    }

    public function testOccurrencesAreAskedBetweenMoments(): void
    {
        $recurrence = Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=DAILY'));

        self::assertSame(['11-04', '11-05', '11-06'], $this->days($recurrence->between(self::at('2026-11-04 09:00'), self::at('2026-11-06 09:00'))));
        self::assertSame(['11-04', '11-05'], $this->days($recurrence->between(self::at('2026-11-03 09:01'), self::at('2026-11-06 08:59'))));
        self::assertSame(['11-02', '11-03'], $this->days($recurrence->between(limit: 2)));
        self::assertSame([], $recurrence->between(limit: 0));
        self::assertSame(['11-02'], $this->days($recurrence->between(limit: 1)));
        self::assertSame('2026-11-04', $recurrence->next(self::at('2026-11-03 09:00'))?->format('Y-m-d'));
        self::assertNull(Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse('FREQ=DAILY;COUNT=2'))->next(self::at('2026-11-03 09:00')));
        self::assertSame($recurrence->start, $recurrence->between()[0]);
        self::assertSame('FREQ=DAILY', (string) $recurrence->rule);
    }

    /**
     * @param list<DateTimeImmutable> $occurrences
     *
     * @return list<string>
     */
    private function days(array $occurrences): array
    {
        return array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $occurrences);
    }

    public function testAnEndIsTheLastMomentAnOccurrenceMayStart(): void
    {
        $days = static fn (string $rule): array => array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), Recurrence::of(self::at('2026-11-02 09:00'), RecurrenceRule::parse($rule, new DateTimeZone('America/Toronto')))->between());

        self::assertSame(['11-02', '11-03'], $days('FREQ=DAILY;UNTIL=20261103T090000'));
        self::assertSame(['11-02'], $days('FREQ=DAILY;UNTIL=20261103T085959'));
        self::assertSame(['11-02', '11-03'], $days('FREQ=DAILY;UNTIL=20261103T140000Z'));
        self::assertSame(['11-02', '11-03'], $days('FREQ=DAILY;UNTIL=20261103'));
        self::assertSame([], $days('FREQ=DAILY;UNTIL=20261101'));

        $late = Recurrence::of(self::at('2026-11-02 23:59:59'), RecurrenceRule::parse('FREQ=DAILY;UNTIL=20261103'))->between();
        self::assertSame(['11-02', '11-03'], $this->days($late));
    }

    public function testARuleThatMatchesNothingStops(): void
    {
        $occurrences = Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=30'))->between();

        self::assertSame(['01-01'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('m-d'), $occurrences));
        self::assertSame(10_000, Recurrence::MAX_PERIODS);
    }

    /**
     * Every 110 days, a February 29 comes in 1892, then not before 4904: 10,000 periods without a day in between.
     */
    public function testTheLimitOfEmptyPeriodsCountsThePeriodsInARowWithoutADay(): void
    {
        $next = static function (string $start): ?string {
            $first = new DateTimeImmutable($start.' 09:00', new DateTimeZone('UTC'));

            return Recurrence::of($first, RecurrenceRule::parse('FREQ=DAILY;INTERVAL=110;BYMONTH=2;BYMONTHDAY=29'))->next($first)?->format('Y-m-d');
        };

        self::assertNull($next('1892-02-29'), 'a day, then 10,000 periods without one');
        self::assertNull($next('1892-06-18'), '10,000 periods without a day from the first one');
        self::assertSame('4904-02-29', $next('1892-10-06'), '9,999 periods without a day, then one');
    }

    public function testAnEndlessRuleKeepsGoingPastTheLimitOfEmptyPeriods(): void
    {
        $daily = Recurrence::of(new DateTimeImmutable('2000-01-01 10:00', new DateTimeZone('America/Toronto')), RecurrenceRule::parse('FREQ=DAILY'));

        self::assertSame('2030-01-02 10:00', $daily->next(new DateTimeImmutable('2030-01-01 12:00', new DateTimeZone('America/Toronto')))?->format('Y-m-d H:i'));

        $reached = null;

        foreach ($daily->occurrences() as $index => $occurrence) {
            if (19_496 === $index) {
                $reached = $occurrence->format('Y-m-d');

                break;
            }
        }

        self::assertSame('2053-05-18', $reached);

        $monthly = Recurrence::of(self::at('2026-01-13 19:00'), RecurrenceRule::parse('FREQ=MONTHLY;BYDAY=2TU'));
        self::assertSame(['9999-11-09'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d'), $monthly->between(self::at('9999-11-01 00:00'), self::at('9999-11-30 23:59'))));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function windows(): iterable
    {
        yield 'every third day' => ['2026-01-01 09:00', 'FREQ=DAILY;INTERVAL=3', '2031-07-04 00:00', '2031-08-04 00:00'];
        yield 'every other week' => ['2026-01-07 19:00', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;WKST=SU', '2034-02-01 00:00', '2034-03-15 00:00'];
        yield 'last weekday of every second month' => ['2026-01-30 12:00', 'FREQ=MONTHLY;INTERVAL=2;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1', '2040-02-01 00:00', '2041-01-01 00:00'];
        yield 'two days of every third year' => ['2020-01-01 08:00', 'FREQ=YEARLY;INTERVAL=3;BYMONTHDAY=1,15;BYMONTH=3', '2044-01-01 00:00', '2051-12-31 00:00'];
        yield 'a window that starts before the first one' => ['2026-05-10 09:00', 'FREQ=WEEKLY', '2026-01-01 00:00', '2026-06-01 00:00'];
        yield 'a window at the edge of a day in another zone' => ['2026-01-01 23:30', 'FREQ=DAILY', '2027-03-05T04:00:00Z', '2027-03-07T05:00:00Z'];
        yield 'an end before the window' => ['2026-01-01 09:00', 'FREQ=DAILY;UNTIL=20270101', '2028-01-01 00:00', '2029-01-01 00:00'];
    }

    #[DataProvider('windows')]
    public function testAWindowGivesWhatAWalkFromTheFirstOccurrenceGives(string $start, string $rule, string $from, string $through): void
    {
        $recurrence = Recurrence::of(self::at($start), RecurrenceRule::parse($rule), [self::at($start)->modify('+30 years')]);
        $walked = array_values(array_filter(
            iterator_to_array((static function () use ($recurrence, $through): iterable {
                foreach ($recurrence->occurrences() as $occurrence) {
                    if ($occurrence > self::at($through)) {
                        return;
                    }

                    yield $occurrence;
                }
            })(), false),
            static fn (DateTimeImmutable $occurrence): bool => $occurrence >= self::at($from),
        ));

        self::assertEquals($walked, $recurrence->between(self::at($from), self::at($through)));
        self::assertEquals($walked[0] ?? null, $recurrence->next(self::at($from)->modify('-1 second')));
    }

    public function testACountedRuleIsStillCountedFromItsFirstOccurrence(): void
    {
        $recurrence = Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=DAILY;COUNT=10'));

        self::assertSame(['01-09', '01-10'], $this->days($recurrence->between(self::at('2026-01-09 00:00'))));
        self::assertNull($recurrence->next(self::at('2026-01-10 09:00')));
    }

    public function testARuleThatMatchesNothingStopsAtTheEndOfTheWindow(): void
    {
        $recurrence = Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30'));

        self::assertSame([], $recurrence->between(self::at('2027-01-01 00:00'), self::at('2028-01-01 00:00')));
    }

    /**
     * Days are laid out as numbers, far from 1970 as near it: the 25th century has its leap day.
     */
    public function testDaysFarFromNowAreTheirOwn(): void
    {
        $days = static fn (string $start, string $rule): array => array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d D'), Recurrence::of(self::at($start), RecurrenceRule::parse($rule))->between());

        self::assertSame(['2400-02-28 Mon', '2400-02-29 Tue', '2400-03-01 Wed'], $days('2400-02-28 09:00', 'FREQ=DAILY;COUNT=3'));
        self::assertSame(['2400-02-28 Mon', '2400-02-29 Tue', '2400-03-06 Mon'], $days('2400-02-28 09:00', 'FREQ=WEEKLY;BYDAY=MO,TU;COUNT=3'));
        self::assertSame(['1900-02-28 Wed', '1900-03-01 Thu', '1900-03-31 Sat'], $days('1900-02-28 09:00', 'FREQ=DAILY;BYMONTHDAY=1,-1;COUNT=3'));
        self::assertSame(['2028-01-15 Sat', '2028-03-01 Wed'], $days('2028-01-15 09:00', 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1;COUNT=2'), 'March of a leap year');
        self::assertSame(['2028-01-15 Sat', '2028-12-31 Sun'], $days('2028-01-15 09:00', 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=31;COUNT=2'));
    }

    public function testTheOccurrencesStopAtTheEndOfTheYear9999(): void
    {
        $last = static fn (Recurrence $recurrence): ?string => array_last(array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d'), $recurrence->between(self::at('9999-12-01 00:00'))));

        self::assertSame('9999-12-31', $last(Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=DAILY;UNTIL=99991231'))));
        self::assertSame('9999-12-31', $last(Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=DAILY'))));
        self::assertSame('9999-12-27', $last(Recurrence::of(self::at('2026-01-04 09:00'), RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=MO,SU'))));
        self::assertNull(Recurrence::of(self::at('2026-01-01 09:00'), RecurrenceRule::parse('FREQ=DAILY'))->next(self::at('9999-12-31 12:00')));
    }

    /**
     * Leap days, 1,461 days apart and none in 2100: 50,000 periods from 2024-02-29 reach 2161-01-20, and hold 34 of them.
     */
    public function testAWindowWalksNoMorePeriodsThanItsBudget(): void
    {
        $leapDays = Recurrence::of(new DateTimeImmutable('2024-02-29 09:00', new DateTimeZone('UTC')), RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=29'));
        $between = $leapDays->between(limit: 1000);

        self::assertSame(50_000, Recurrence::MAX_WALKED);
        self::assertSame(['2024-02-29', '2028-02-29'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d'), \array_slice($between, 0, 2)));
        self::assertCount(34, $between);
        self::assertSame('2160-02-29', array_last($between)->format('Y-m-d'));
        self::assertSame(['2504-02-29', '2508-02-29'], array_map(static fn (DateTimeImmutable $occurrence): string => $occurrence->format('Y-m-d'), $leapDays->between(new DateTimeImmutable('2500-01-01', new DateTimeZone('UTC')), limit: 2)), 'a window skips to its start before it walks');
    }

    /**
     * The budget's last period is walked, the next one is not: 2164-02-29 lies 50,000 days after 2027-04-09.
     */
    public function testTheBudgetEndsOnItsLastPeriod(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=29');

        self::assertSame('2164-02-29', array_last(Recurrence::of(new DateTimeImmutable('2027-04-09 09:00', new DateTimeZone('UTC')), $rule)->between(limit: 1000))?->format('Y-m-d'));
        self::assertSame('2160-02-29', array_last(Recurrence::of(new DateTimeImmutable('2027-04-08 09:00', new DateTimeZone('UTC')), $rule)->between(limit: 1000))?->format('Y-m-d'));
    }

    public function testACountedRuleWalksNoMorePeriodsThanItsBudget(): void
    {
        $leapDays = Recurrence::of(new DateTimeImmutable('2024-02-29 09:00', new DateTimeZone('UTC')), RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=29;COUNT=9999'));
        $started = hrtime(true);

        self::assertNull($leapDays->next(new DateTimeImmutable('9999-12-01', new DateTimeZone('UTC'))));
        self::assertSame('2160-02-29', $leapDays->next(new DateTimeImmutable('2160-01-01', new DateTimeZone('UTC')))?->format('Y-m-d'));
        self::assertNull($leapDays->next(new DateTimeImmutable('2160-03-01', new DateTimeZone('UTC'))));
        self::assertLessThan(10_000_000_000, hrtime(true) - $started);
    }

    public function testEveryOccurrenceIsWalkedPastTheBudget(): void
    {
        $reached = null;

        foreach (Recurrence::of(new DateTimeImmutable('2024-02-29 09:00', new DateTimeZone('UTC')), RecurrenceRule::parse('FREQ=DAILY;BYMONTH=2;BYMONTHDAY=29'))->occurrences() as $index => $occurrence) {
            if (40 === $index) {
                $reached = $occurrence->format('Y-m-d');

                break;
            }
        }

        self::assertSame('2188-02-29', $reached);
    }
}
