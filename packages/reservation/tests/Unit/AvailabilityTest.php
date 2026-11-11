<?php

declare(strict_types=1);

namespace Phil\Reservation\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Reservation\Availability\AlwaysAvailable;
use Phil\Reservation\Availability\WeeklyAvailability;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Exception\InvalidInterval;
use Phil\Reservation\Interval;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlwaysAvailable::class)]
#[CoversClass(WeeklyAvailability::class)]
final class AvailabilityTest extends TestCase
{
    private function zone(): DateTimeZone
    {
        return new DateTimeZone('America/Toronto');
    }

    private function span(string $start, string $end, ?DateTimeZone $zone = null): Interval
    {
        return new Interval(new DateTimeImmutable($start, $zone ?? $this->zone()), new DateTimeImmutable($end, $zone ?? $this->zone()));
    }

    /**
     * @param list<Interval> $windows
     *
     * @return list<string>
     */
    private function written(array $windows): array
    {
        return array_map(static fn (Interval $window): string => $window->start->format('D Y-m-d H:i').' – '.$window->end->format('D Y-m-d H:i T'), $windows);
    }

    public function testAResourceAlwaysOpenGivesThePeriodBack(): void
    {
        $period = $this->span('2026-11-10 09:00', '2026-11-10 10:00');

        self::assertSame([$period], new AlwaysAvailable()->windows($period));
    }

    public function testWeeklyHoursGiveTheSpansOfAPeriod(): void
    {
        // Monday 2026-11-09 to Wednesday 2026-11-11; Tuesday has two ranges, given out of order.
        $hours = new WeeklyAvailability([2 => [[780, 1020], [540, 720]], 1 => [[540, 1020]]], $this->zone());

        self::assertSame([1 => [[540, 1020]], 2 => [[540, 720], [780, 1020]]], $hours->hours);
        self::assertSame($this->zone()->getName(), $hours->timezone->getName());
        self::assertSame([
            'Mon 2026-11-09 09:00 – Mon 2026-11-09 17:00 EST',
            'Tue 2026-11-10 09:00 – Tue 2026-11-10 12:00 EST',
            'Tue 2026-11-10 13:00 – Tue 2026-11-10 17:00 EST',
        ], $this->written($hours->windows($this->span('2026-11-09 00:00', '2026-11-12 00:00'))));
    }

    public function testSpansAreWholeAndOnlyThoseMeetingThePeriod(): void
    {
        $hours = new WeeklyAvailability([2 => [[540, 720], [780, 1020]]], $this->zone());

        self::assertSame(['Tue 2026-11-10 09:00 – Tue 2026-11-10 12:00 EST'], $this->written($hours->windows($this->span('2026-11-10 11:00', '2026-11-10 13:00'))));
        self::assertSame(['Tue 2026-11-10 13:00 – Tue 2026-11-10 17:00 EST'], $this->written($hours->windows($this->span('2026-11-10 12:00', '2026-11-10 13:01'))));
        self::assertSame(['Tue 2026-11-10 09:00 – Tue 2026-11-10 12:00 EST'], $this->written($hours->windows($this->span('2026-11-10 08:00', '2026-11-10 09:01'))));
        self::assertSame([], $hours->windows($this->span('2026-11-10 12:00', '2026-11-10 13:00')));
        self::assertSame([], $hours->windows($this->span('2026-11-10 17:00', '2026-11-10 23:00')));
        self::assertSame([], $hours->windows($this->span('2026-11-11 09:00', '2026-11-11 17:00')));
    }

    public function testRangesThatTouchMakeOneSpanAcrossMidnight(): void
    {
        // Friday from 20:00 to midnight, Saturday from midnight to 2:00 and from 2:00 to 3:00.
        $hours = new WeeklyAvailability([5 => [[1200, 1440]], 6 => [[0, 120], [120, 180]]], $this->zone());

        self::assertSame(['Fri 2026-11-13 20:00 – Sat 2026-11-14 03:00 EST'], $this->written($hours->windows($this->span('2026-11-14 01:00', '2026-11-14 02:00'))));
        self::assertSame(['Fri 2026-11-13 20:00 – Sat 2026-11-14 03:00 EST'], $this->written($hours->windows($this->span('2026-11-13 21:00', '2026-11-13 22:00'))));
    }

    public function testRangesOnTheSameDayThatOverlapOnTheirEdgeJoin(): void
    {
        $hours = new WeeklyAvailability([2 => [[540, 720], [720, 800], [800, 900]]], $this->zone());

        self::assertSame(['Tue 2026-11-10 09:00 – Tue 2026-11-10 15:00 EST'], $this->written($hours->windows($this->span('2026-11-10 00:00', '2026-11-11 00:00'))));
    }

    public function testADayClosedHasNoSpan(): void
    {
        $hours = new WeeklyAvailability([1 => [[540, 1020]], 2 => [[540, 1020]], 3 => [[540, 1020]]], $this->zone(), ['2026-11-10']);

        self::assertSame([
            'Mon 2026-11-09 09:00 – Mon 2026-11-09 17:00 EST',
            'Wed 2026-11-11 09:00 – Wed 2026-11-11 17:00 EST',
        ], $this->written($hours->windows($this->span('2026-11-09 00:00', '2026-11-12 00:00'))));
    }

    public function testAPeriodGivenInAnotherZoneIsReadOnTheResourcesClock(): void
    {
        $hours = new WeeklyAvailability([2 => [[540, 1020]]], $this->zone());

        // Tuesday 3:00 to Wednesday 3:00 in Paris is Monday 21:00 to Tuesday 21:00 in Toronto.
        self::assertSame(['Tue 2026-11-10 09:00 – Tue 2026-11-10 17:00 EST'], $this->written($hours->windows($this->span('2026-11-10 03:00', '2026-11-11 03:00', new DateTimeZone('Europe/Paris')))));
    }

    public function testTheHoursKeepTheirWallClockAcrossAChangeOfTime(): void
    {
        $hours = new WeeklyAvailability([7 => [[60, 240]]], $this->zone());

        // 2026-03-08 at 2:00 the clocks go forward: 1:00 to 4:00 lasts two hours.
        $windows = $hours->windows($this->span('2026-03-08 00:00', '2026-03-09 00:00'));
        self::assertSame(['Sun 2026-03-08 01:00 – Sun 2026-03-08 04:00 EDT'], $this->written($windows));
        self::assertSame(7200, $windows[0]->seconds());
    }

    public function testAPeriodIsReadOverAThousandDaysAtMost(): void
    {
        $hours = new WeeklyAvailability([1 => [[540, 1020]]], $this->zone());

        self::assertCount(143, $hours->windows($this->span('2026-01-01 00:00', '2028-09-27 00:00')));
        $this->expectException(InvalidInterval::class);
        $this->expectExceptionMessageIsOrContains('Weekly hours are read over 1000 days at most.');

        $hours->windows($this->span('2026-01-01 00:00', '2028-09-28 00:00'));
    }

    /**
     * @return iterable<string, array{array<int, list<array{int, int}>>, list<string>, string}>
     */
    public static function badHours(): iterable
    {
        yield 'weekday zero' => [[0 => [[540, 600]]], [], 'A weekday is numbered from 1 for Monday to 7 for Sunday, not 0.'];
        yield 'weekday eight' => [[8 => [[540, 600]]], [], 'A weekday is numbered from 1 for Monday to 7 for Sunday, not 8.'];
        yield 'before midnight' => [[1 => [[-1, 600]]], [], 'A range of hours goes from a minute of the day to a later one, up to 1440: not -1 to 600.'];
        yield 'after midnight' => [[1 => [[540, 1441]]], [], 'A range of hours goes from a minute of the day to a later one, up to 1440: not 540 to 1441.'];
        yield 'empty' => [[1 => [[540, 540]]], [], 'A range of hours goes from a minute of the day to a later one, up to 1440: not 540 to 540.'];
        yield 'overlapping' => [[1 => [[540, 700], [600, 800]]], [], 'The ranges of hours of a day do not overlap.'];
        yield 'closed day unreadable' => [[], ['10/11/2026'], 'A closed day is written as Y-m-d, not "10/11/2026".'];
        yield 'closed day that does not exist' => [[], ['2026-02-30'], 'A closed day is written as Y-m-d, not "2026-02-30".'];
    }

    /**
     * @param array<int, list<array{int, int}>> $hours
     * @param list<string>                      $closed
     */
    #[DataProvider('badHours')]
    public function testHoursAreChecked(array $hours, array $closed, string $message): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains($message);

        new WeeklyAvailability($hours, $this->zone(), $closed);
    }

    public function testMinutesThatAreNotWholeHoursAreRead(): void
    {
        $hours = new WeeklyAvailability([2 => [[590, 1190]]], $this->zone());

        self::assertSame(['Tue 2026-11-10 09:50 – Tue 2026-11-10 19:50 EST'], $this->written($hours->windows($this->span('2026-11-10 00:00', '2026-11-11 00:00'))));
    }

    public function testRangesGivenOutOfOrderAreSortedAndChecked(): void
    {
        $hours = new WeeklyAvailability([2 => [[720, 800], [540, 720], [900, 960], [0, 60]]], $this->zone());

        self::assertSame([2 => [[0, 60], [540, 720], [720, 800], [900, 960]]], $hours->hours);

        $this->expectException(InvalidDefinition::class);

        new WeeklyAvailability([2 => [[600, 700], [540, 650]]], $this->zone());
    }

    public function testRangesStartingTogetherOverlap(): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('The ranges of hours of a day do not overlap.');

        new WeeklyAvailability([2 => [[540, 600], [540, 700]]], $this->zone());
    }

    public function testAClosedDayIsQuotedOnFortyCharactersAtMost(): void
    {
        try {
            new WeeklyAvailability([], $this->zone(), [str_repeat('é', 41)]);
            self::fail('The closed day is refused.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('A closed day is written as Y-m-d, not "'.str_repeat('é', 40).'".', $invalidDefinition->getMessage());
        }
    }

    public function testAPeriodEndingAtMidnightReadsItsLastDay(): void
    {
        $hours = new WeeklyAvailability([3 => [[0, 60]]], $this->zone());

        // The period ends on Wednesday at 0:30: Wednesday's range from midnight meets it.
        self::assertSame(['Wed 2026-11-11 00:00 – Wed 2026-11-11 01:00 EST'], $this->written($hours->windows($this->span('2026-11-10 22:00', '2026-11-11 00:30'))));
    }

    public function testFullDaysAreAccepted(): void
    {
        $hours = new WeeklyAvailability([1 => [[0, 1440]], 7 => [[0, 1]]], $this->zone());

        self::assertSame(['Mon 2026-11-09 00:00 – Tue 2026-11-10 00:00 EST'], $this->written($hours->windows($this->span('2026-11-09 12:00', '2026-11-09 13:00'))));
    }
}
