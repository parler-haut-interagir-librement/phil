<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Tests\Unit;

use DateTimeImmutable;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Phil\OpeningHours\HoursText;
use Phil\OpeningHours\OpeningHours;
use Phil\OpeningHours\SpecialHours;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpeningHours::class)]
#[CoversClass(SpecialHours::class)]
#[CoversClass(InvalidOpeningHours::class)]
final class OpeningHoursTest extends TestCase
{
    private function shop(): OpeningHours
    {
        return OpeningHours::of(
            [
                1 => HoursText::day('09:00-17:00'),
                2 => HoursText::day('09:00-17:00'),
                3 => HoursText::day('09:00-17:00'),
                4 => HoursText::day('09:00-12:00, 13:00-21:00'),
                5 => HoursText::day('09:00-17:00, 20:00-02:00'),
                6 => HoursText::day('10:00-16:00'),
            ],
            [SpecialHours::of('2026-12-25', label: 'Christmas'), SpecialHours::of('2026-12-24', '2026-12-31', HoursText::day('10:00-14:00')), SpecialHours::of('2026-12-24', hours: HoursText::day('08:00-09:00'))],
            'America/Toronto',
        );
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function moments(): iterable
    {
        yield 'before opening' => ['2026-11-02 08:59 America/Toronto', false];
        yield 'at opening' => ['2026-11-02 09:00 America/Toronto', true];
        yield 'before closing' => ['2026-11-02 16:59 America/Toronto', true];
        yield 'at closing' => ['2026-11-02 17:00 America/Toronto', false];
        yield 'at lunch' => ['2026-11-05 12:30 America/Toronto', false];
        yield 'in another time zone' => ['2026-11-02 14:30 UTC', true];
        yield 'too early in another time zone' => ['2026-11-02 13:59 UTC', false];
        yield 'past midnight' => ['2026-11-07 01:59 America/Toronto', true];
        yield 'after the night' => ['2026-11-07 02:00 America/Toronto', false];
        yield 'on Sunday' => ['2026-11-08 12:00 America/Toronto', false];
        yield 'on a closed holiday' => ['2026-12-25 11:00 America/Toronto', false];
        yield 'in a special period' => ['2026-12-28 13:59 America/Toronto', true];
        yield 'after a special period closes' => ['2026-12-28 14:00 America/Toronto', false];
        yield 'the first special wins' => ['2026-12-24 08:30 America/Toronto', false];
    }

    #[DataProvider('moments')]
    public function testAPlaceIsOpenOrNotAtAMoment(string $moment, bool $open): void
    {
        self::assertSame($open, $this->shop()->isOpenAt(new DateTimeImmutable($moment)));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function openings(): iterable
    {
        yield 'later today' => ['2026-11-02 07:00 America/Toronto', '2026-11-02 09:00'];
        yield 'after lunch' => ['2026-11-05 12:30 America/Toronto', '2026-11-05 13:00'];
        yield 'open now, the next opening' => ['2026-11-02 09:00 America/Toronto', '2026-11-03 09:00'];
        yield 'over the weekend' => ['2026-11-07 17:00 America/Toronto', '2026-11-09 09:00'];
        yield 'over a holiday' => ['2026-12-24 15:00 America/Toronto', '2026-12-26 10:00'];
        yield 'from another time zone' => ['2026-11-02 23:00 UTC', '2026-11-03 09:00'];
    }

    #[DataProvider('openings')]
    public function testAPlaceNextOpensInItsTimeZone(string $moment, ?string $opening): void
    {
        $next = $this->shop()->nextOpening(new DateTimeImmutable($moment));

        self::assertSame($opening, $next?->format('Y-m-d H:i'));
        self::assertSame('America/Toronto', $next?->getTimezone()->getName());
    }

    public function testAPlaceAlwaysClosedNeverOpens(): void
    {
        $closed = OpeningHours::of([]);

        self::assertNull($closed->nextOpening(new DateTimeImmutable('2026-11-02 12:00')));
        self::assertTrue($closed->isAlwaysClosed());
        self::assertFalse($this->shop()->isAlwaysClosed());
        self::assertSame('UTC', $closed->timezone);
        self::assertSame('2027-11-02 09:00', OpeningHours::of([], [SpecialHours::of('2027-11-02', hours: HoursText::day('09:00-10:00'))])->nextOpening(new DateTimeImmutable('2026-11-01 12:00'))?->format('Y-m-d H:i'));
        self::assertNull(OpeningHours::of([], [SpecialHours::of('2027-11-03', hours: HoursText::day('09:00-10:00'))])->nextOpening(new DateTimeImmutable('2026-11-01 12:00')));
    }

    /**
     * A place the week keeps closed opens on the first of its special days ahead that has hours, in its time zone.
     */
    public function testAClosedPlaceOpensOnItsNextSpecialDay(): void
    {
        $nine = HoursText::day('09:00-10:00');
        $places = static fn (SpecialHours ...$specials): OpeningHours => OpeningHours::of([], array_values($specials), 'America/Toronto');
        $next = static fn (OpeningHours $place, string $moment): ?string => $place->nextOpening(new DateTimeImmutable($moment))?->format('Y-m-d H:i');

        self::assertSame('2026-12-01 09:00', $next($places(SpecialHours::of('2026-12-01', hours: $nine), SpecialHours::of('2026-11-20', hours: $nine)), '2026-11-25 12:00 America/Toronto'));
        self::assertSame('2026-11-20 09:00', $next($places(SpecialHours::of('2026-12-01', hours: $nine), SpecialHours::of('2026-11-20', hours: $nine)), '2026-11-19 12:00 America/Toronto'));
        self::assertSame('2026-11-26 09:00', $next($places(SpecialHours::of('2026-11-01', '2026-11-30', $nine)), '2026-11-25 12:00 America/Toronto'));
        self::assertSame('2026-11-25 09:00', $next($places(SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-25 08:00 America/Toronto'));
        self::assertSame('2026-11-25 09:00', $next($places(SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-25 13:00 UTC'));
        self::assertNull($next($places(SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-25 09:00 America/Toronto'));
        self::assertNull($next($places(SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-26 03:00 UTC'));
        self::assertNull($next($places(SpecialHours::of('2026-11-20', '2026-11-24', $nine)), '2026-11-25 08:00 America/Toronto'));
        self::assertNull($next($places(SpecialHours::of('2026-11-25'), SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-24 12:00 America/Toronto'));
        self::assertSame('2026-11-25 08:00', $next($places(SpecialHours::of('2026-11-25', hours: HoursText::day('08:00-09:00')), SpecialHours::of('2026-11-25', hours: $nine)), '2026-11-24 12:00 America/Toronto'));
        self::assertSame('2026-11-26 09:00', $next($places(SpecialHours::of('2020-01-01', '2030-12-31', $nine)), '2026-11-25 12:00 America/Toronto'));
    }

    /**
     * A year of special days closed puts the next opening a year away, and no further.
     */
    public function testAYearClosedIsTheFurthestNextOpening(): void
    {
        $everyDay = array_fill(1, 7, HoursText::day('09:00-17:00'));

        self::assertSame('2027-11-26 09:00', OpeningHours::of($everyDay, [SpecialHours::of('2026-11-25', '2027-11-25')])->nextOpening(new DateTimeImmutable('2026-11-25 08:00'))?->format('Y-m-d H:i'));
        self::assertNull(OpeningHours::of($everyDay, [SpecialHours::of('2026-11-25', '2027-11-26')])->nextOpening(new DateTimeImmutable('2026-11-25 08:00')));
        self::assertSame('2026-11-30 09:00', OpeningHours::of([1 => HoursText::day('09:00-17:00')], [SpecialHours::of('2026-11-23')])->nextOpening(new DateTimeImmutable('2026-11-23 08:00'))?->format('Y-m-d H:i'));
        self::assertSame('2026-11-29 09:00', OpeningHours::of([7 => HoursText::day('09:00-17:00')], [SpecialHours::of('2026-11-22')])->nextOpening(new DateTimeImmutable('2026-11-22 08:00'))?->format('Y-m-d H:i'));
    }

    public function testADayHasItsWeekdaysOrItsSpecialHours(): void
    {
        $shop = $this->shop();

        self::assertSame('09:00-12:00, 13:00-21:00', $shop->on(DayOfWeek::Thursday)->toText());
        self::assertTrue($shop->on(DayOfWeek::Sunday)->isClosed());
        self::assertSame('10:00-14:00', $shop->hoursOn(new DateTimeImmutable('2026-12-31'))->toText());
        self::assertSame('Christmas', $shop->specialOn(new DateTimeImmutable('2026-12-25'))?->label);
        self::assertNull($shop->specialOn(new DateTimeImmutable('2027-01-01')));
        self::assertSame('09:00-17:00', $shop->hoursOn(new DateTimeImmutable('2027-01-04'))->toText());
    }

    public function testTheWeekIsGroupedInRunsOfSameHours(): void
    {
        $groups = $this->shop()->groups();

        self::assertSame([[1, 2, 3], [4], [5], [6], [7]], array_map(static fn (array $group): array => array_map(static fn (DayOfWeek $day): int => $day->value, $group['days']), $groups));
        self::assertSame(['09:00-17:00', '09:00-12:00, 13:00-21:00', '09:00-17:00, 20:00-02:00', '10:00-16:00', ''], array_map(static fn (array $group): string => $group['hours']->toText(), $groups));
        self::assertCount(1, OpeningHours::of([])->groups());
    }

    public function testATimeZoneIsKnown(): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains('"Mars/Olympus" is not a time zone.');

        OpeningHours::of([], [], 'Mars/Olympus');
    }

    public function testSpecialHoursCoverAPeriodOfDays(): void
    {
        $period = SpecialHours::of('2026-12-24', '2026-12-26', label: '  Holidays ');

        self::assertSame(['2026-12-24', '2026-12-26', 'Holidays', true], [$period->from, $period->through, $period->label, $period->hours->isClosed()]);
        self::assertSame([false, true, true, false], [$period->covers(new DateTimeImmutable('2026-12-23')), $period->covers(new DateTimeImmutable('2026-12-24')), $period->covers(new DateTimeImmutable('2026-12-26 23:59')), $period->covers(new DateTimeImmutable('2026-12-27'))]);
        self::assertSame(['2026-12-25', null], [SpecialHours::of('2026-12-25')->through, SpecialHours::of('2026-12-25', label: ' ')->label]);
        self::assertSame(str_repeat('é', 100), SpecialHours::of('2026-12-25', label: str_repeat('é', 100))->label);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function badPeriods(): iterable
    {
        yield 'no date' => ['Christmas', null, null, 'From Christmas through Christmas is no period of days.'];
        yield 'no such day' => ['2026-02-30', null, null, 'From 2026-02-30 through 2026-02-30 is no period of days.'];
        yield 'a bad last day' => ['2026-12-24', '2026-12-32', null, 'From 2026-12-24 through 2026-12-32 is no period of days.'];
        yield 'backwards' => ['2026-12-26', '2026-12-24', null, 'From 2026-12-26 through 2026-12-24 is no period of days.'];
        yield 'a long label' => ['2026-12-25', null, str_repeat('a', 101), 'The label of special hours is 100 characters or fewer.'];
    }

    #[DataProvider('badPeriods')]
    public function testABadPeriodIsRefused(string $from, ?string $through, ?string $label, string $message): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains($message);

        SpecialHours::of($from, $through, label: $label);
    }

    public function testTheMinuteOfTheDayIsRead(): void
    {
        $hours = OpeningHours::of([1 => HoursText::day('10:30-11:00, 19:00-20:00')], [], 'America/Toronto');

        self::assertFalse($hours->isOpenAt(new DateTimeImmutable('2026-11-02 10:29:59 America/Toronto')));
        self::assertTrue($hours->isOpenAt(new DateTimeImmutable('2026-11-02 10:30 America/Toronto')));
        self::assertTrue($hours->isOpenAt(new DateTimeImmutable('2026-11-02 19:59:59 America/Toronto')));
        self::assertFalse($hours->isOpenAt(new DateTimeImmutable('2026-11-02 20:00 America/Toronto')));
        self::assertTrue($hours->isOpenAt(new DateTimeImmutable('1960-11-07 10:30 America/Toronto')));
        self::assertFalse($hours->isOpenAt(new DateTimeImmutable('1960-11-07 10:29:59 America/Toronto')));
        self::assertTrue($hours->isOpenAt(new DateTimeImmutable('2027-03-15 19:59 America/Toronto')));
    }

    public function testADayWithoutHoursIsClosed(): void
    {
        self::assertTrue(OpeningHours::of([1 => DayHours::closed()])->on(DayOfWeek::Monday)->isClosed());
    }
}
