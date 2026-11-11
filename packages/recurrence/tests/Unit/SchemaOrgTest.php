<?php

declare(strict_types=1);

namespace Phil\Recurrence\Tests\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use Phil\Recurrence\SchemaOrg;
use Phil\Recurrence\Weekday;
use Phil\Recurrence\WeekdayNum;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
    public function testARecurrenceIsASchedule(): void
    {
        $start = new DateTimeImmutable('2026-11-10 19:30', new DateTimeZone('America/Toronto'));
        $weekly = Recurrence::of($start, RecurrenceRule::of(Frequency::Weekly, 2, until: new DateTimeImmutable('2027-06-30 23:59 UTC'), byDay: [new WeekdayNum(Weekday::Tuesday), new WeekdayNum(Weekday::Thursday)]), [new DateTime('2026-12-22 19:30', new DateTimeZone('America/Toronto'))]);

        self::assertSame([
            '@type' => 'Schedule',
            'startDate' => '2026-11-10',
            'startTime' => '19:30:00',
            'repeatFrequency' => 'P2W',
            'byDay' => ['https://schema.org/Tuesday', 'https://schema.org/Thursday'],
            'endDate' => '2027-06-30',
            'exceptDate' => ['2026-12-22'],
            'scheduleTimezone' => 'America/Toronto',
        ], SchemaOrg::of($weekly));

        $monthly = Recurrence::of(new DateTimeImmutable('2026-11-10 09:00 UTC'), RecurrenceRule::of(Frequency::Monthly, 1, 12, byDay: [new WeekdayNum(Weekday::Tuesday, 2), new WeekdayNum(Weekday::Sunday, -1)], byMonth: [1, 6]));
        self::assertSame(['@type' => 'Schedule', 'startDate' => '2026-11-10', 'startTime' => '09:00:00', 'repeatFrequency' => 'P1M', 'byDay' => ['2TU', '-1SU'], 'byMonth' => [1, 6], 'repeatCount' => 12, 'scheduleTimezone' => 'UTC'], SchemaOrg::of($monthly));

        $yearly = Recurrence::of(new DateTimeImmutable('2026-01-01 00:00 UTC'), RecurrenceRule::of(Frequency::Yearly, 3, byMonthDay: [1, 15]));
        self::assertSame(['P3Y', [1, 15]], [SchemaOrg::of($yearly)['repeatFrequency'], SchemaOrg::of($yearly)['byMonthDay']]);
        self::assertSame('P1D', SchemaOrg::of(Recurrence::of(new DateTimeImmutable(), RecurrenceRule::of(Frequency::Daily)))['repeatFrequency']);
        self::assertSame(['@type', 'startDate', 'startTime', 'repeatFrequency', 'scheduleTimezone'], array_keys(SchemaOrg::of(Recurrence::of(new DateTimeImmutable(), RecurrenceRule::of(Frequency::Daily)))));
        self::assertSame(['https://schema.org/Monday', 'https://schema.org/Wednesday', 'https://schema.org/Friday', 'https://schema.org/Saturday'], SchemaOrg::of(Recurrence::of(new DateTimeImmutable(), RecurrenceRule::of(Frequency::Weekly, byDay: [new WeekdayNum(Weekday::Monday), new WeekdayNum(Weekday::Wednesday), new WeekdayNum(Weekday::Friday), new WeekdayNum(Weekday::Saturday)])))['byDay']);
    }

    public function testAnEndTimeIsADayOfTheFirstOccurrencesZone(): void
    {
        $start = new DateTimeImmutable('2026-11-10 19:30', new DateTimeZone('America/Toronto'));

        self::assertSame('2026-12-31', SchemaOrg::of(Recurrence::of($start, RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20270101T045959Z')))['endDate']);
        self::assertSame('2026-12-31', SchemaOrg::of(Recurrence::of($start, RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20261231')))['endDate']);
    }
}
