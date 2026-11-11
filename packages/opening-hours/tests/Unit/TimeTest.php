<?php

declare(strict_types=1);

namespace Phil\OpeningHours\Tests\Unit;

use DateTimeImmutable;
use Phil\OpeningHours\DayHours;
use Phil\OpeningHours\DayOfWeek;
use Phil\OpeningHours\Exception\InvalidOpeningHours;
use Phil\OpeningHours\TimeOfDay;
use Phil\OpeningHours\TimeRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TimeOfDay::class)]
#[CoversClass(TimeRange::class)]
#[CoversClass(DayHours::class)]
#[CoversClass(DayOfWeek::class)]
#[CoversClass(InvalidOpeningHours::class)]
final class TimeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function times(): iterable
    {
        yield 'hours' => ['9', '09:00'];
        yield 'hours and h' => ['9h', '09:00'];
        yield 'h and minutes' => ['9h30', '09:30'];
        yield 'capital H' => ['21H15', '21:15'];
        yield 'colon' => [' 09:05 ', '09:05'];
        yield 'midnight' => ['0:00', '00:00'];
        yield 'end of day' => ['24:00', '24:00'];
        yield 'last minute' => ['23:59', '23:59'];
    }

    #[DataProvider('times')]
    public function testATimeIsReadAsPeopleTypeIt(string $input, string $time): void
    {
        self::assertSame($time, (string) TimeOfDay::parse($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notTimes(): iterable
    {
        yield 'letters' => ['noon', '"noon" is not a time of day.'];
        yield 'one minute digit' => ['9:5', '"9:5" is not a time of day.'];
        yield 'three hour digits' => ['009:00', '"009:00" is not a time of day.'];
        yield 'seconds' => ['09:00:00', '"09:00:00" is not a time of day.'];
        yield 'past the end' => ['24:01', '24:01 is not a time of day.'];
        yield 'sixty minutes' => ['9:60', '9:60 is not a time of day.'];
        yield 'twenty-five' => ['25', '25:00 is not a time of day.'];
    }

    #[DataProvider('notTimes')]
    public function testWhatIsNoTimeIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains($message);

        TimeOfDay::parse($input);
    }

    public function testALongInputIsCutInCharactersInTheMessage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('é', 100).'" is not a time of day.');

        TimeOfDay::parse(str_repeat('é', 150));
    }

    public function testARangeIsBuiltFromItsTimes(): void
    {
        self::assertSame('22:00-06:00', (string) TimeRange::of(TimeOfDay::of(22), TimeOfDay::of(6)));
    }

    public function testATimeIsBuiltFromItsParts(): void
    {
        self::assertSame([570, 9], [TimeOfDay::of(9, 30)->minutes, TimeOfDay::of(9, 30)->hours()]);
        self::assertSame(1440, TimeOfDay::of(24)->minutes);
        self::assertTrue(TimeOfDay::of(9)->equals(TimeOfDay::parse('9h')));
        self::assertFalse(TimeOfDay::of(9)->equals(TimeOfDay::of(9, 1)));

        foreach ([[-1, 0], [9, -1]] as [$hours, $minutes]) {
            try {
                TimeOfDay::of($hours, $minutes);
                self::fail('A negative time was taken.');
            } catch (InvalidOpeningHours) {
            }
        }
    }

    public function testARangeIsReadAndMayCloseTheNextDay(): void
    {
        $day = TimeRange::parse('9h - 17h');
        $night = TimeRange::parse('18:00–02:00');

        self::assertSame(['09:00-17:00', false, '18:00-02:00', true], [(string) $day, $day->isOvernight(), (string) $night, $night->isOvernight()]);
        self::assertSame('10:00-24:00', (string) TimeRange::parse('10 to 24'));
        self::assertTrue($day->equals(TimeRange::parse('09:00-17:00')));
        self::assertFalse($day->equals(TimeRange::parse('09:00-17:30')));
        self::assertFalse($day->equals(TimeRange::parse('09:30-17:00')));
    }

    public function testARangeContainsItsMinutes(): void
    {
        $day = TimeRange::parse('09:00-17:00');
        $night = TimeRange::parse('18:00-02:00');

        self::assertSame([false, true, true, false], [$day->contains(539), $day->contains(540), $day->contains(1019), $day->contains(1020)]);
        self::assertSame([false, true, true], [$night->contains(1079), $night->contains(1080), $night->contains(1439)]);
        self::assertSame([true, true, false, false], [$night->spills(0), $night->spills(119), $night->spills(120), $day->spills(0)]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notRanges(): iterable
    {
        yield 'one time' => ['09:00', '"09:00" is not a range of hours.'];
        yield 'three times' => ['09:00-12:00-17:00', '"09:00-12:00-17:00" is not a range of hours.'];
        yield 'empty' => ['09:00-09:00', 'A range from 09:00 to 09:00 is no range.'];
        yield 'opening at the end of the day' => ['24:00-02:00', 'A range from 24:00 to 02:00 is no range.'];
    }

    #[DataProvider('notRanges')]
    public function testWhatIsNoRangeIsRefused(string $input, string $message): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains($message);

        TimeRange::parse($input);
    }

    public function testTheRangesOfADayAreInOrderAndApart(): void
    {
        $hours = DayHours::of([TimeRange::parse('13:00-17:00'), TimeRange::parse('09:00-12:00'), TimeRange::parse('20:00-02:00')]);

        self::assertSame('09:00-12:00, 13:00-17:00, 20:00-02:00', $hours->toText());
        self::assertSame('09:00-12:00, 12:00-17:00', DayHours::of([TimeRange::parse('12:00-17:00'), TimeRange::parse('09:00-12:00')])->toText());
        self::assertFalse($hours->isClosed());
        self::assertTrue(DayHours::closed()->isClosed());
        self::assertSame('', DayHours::closed()->toText());
        self::assertTrue($hours->equals(DayHours::of([TimeRange::parse('09:00-12:00'), TimeRange::parse('13:00-17:00'), TimeRange::parse('20:00-02:00')])));
        self::assertFalse($hours->equals(DayHours::of([TimeRange::parse('09:00-12:00'), TimeRange::parse('13:00-17:00')])));
        self::assertFalse(DayHours::of([TimeRange::parse('09:00-12:00')])->equals(DayHours::of([TimeRange::parse('09:00-13:00')])));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function overlaps(): iterable
    {
        yield 'overlapping' => [['09:00-13:00', '12:00-17:00'], 'The ranges 09:00-13:00 and 12:00-17:00 of a day overlap.'];
        yield 'after an overnight range' => [['00:00-02:00', '22:00-01:00', '23:00-23:30'], 'The ranges 22:00-01:00 and 23:00-23:30 of a day overlap.'];
    }

    /**
     * @param list<string> $ranges
     */
    #[DataProvider('overlaps')]
    public function testOverlappingRangesAreRefused(array $ranges, string $message): void
    {
        $this->expectException(InvalidOpeningHours::class);
        $this->expectExceptionMessageIsOrContains($message);

        DayHours::of(array_map(TimeRange::parse(...), $ranges));
    }

    public function testADayOfTheWeekIsItsIsoNumber(): void
    {
        self::assertSame(DayOfWeek::Tuesday, DayOfWeek::of(new DateTimeImmutable('2026-11-03')));
        self::assertSame(DayOfWeek::Sunday, DayOfWeek::of(new DateTimeImmutable('2026-11-08')));
        self::assertSame([DayOfWeek::Sunday, DayOfWeek::Monday, DayOfWeek::Saturday], [DayOfWeek::Monday->previous(), DayOfWeek::Tuesday->previous(), DayOfWeek::Sunday->previous()]);
        self::assertSame('https://schema.org/Wednesday', DayOfWeek::Wednesday->schemaOrg());
    }
}
