<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Lanes;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Application\View\TimeColumn;
use Phil\CalendarBundle\Application\View\TimedBlock;
use Phil\CalendarBundle\Application\View\TimeGrid;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The days the clocks change: an event across the change, a zone that skips midnight, the hours of a short day.
 */
#[CoversClass(Event::class)]
#[CoversClass(Period::class)]
#[CoversClass(TimeGrid::class)]
#[CoversClass(Lanes::class)]
#[CoversClass(Item::class)]
final class TimeChangesTest extends TestCase
{
    public function testAnOccurrenceEndsAtTheWallClockOfTheEventsEnd(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $event = Event::schedule('Nuit', new DateTimeImmutable('2026-03-07 22:00', $zone), new DateTimeImmutable('2026-03-08 04:00', $zone), false, 'America/Toronto');
        $ends = static fn (Event $event): array => array_map(static fn (Occurrence $occurrence): string => $occurrence->end->format('Y-m-d H:i T'), $event->occurrences(new DateTimeImmutable('2026-03-01', $zone), new DateTimeImmutable('2026-03-12', $zone)));

        self::assertSame(['2026-03-08 04:00 EDT'], $ends($event));

        $event->repeat(RecurrenceRule::parse('FREQ=DAILY;COUNT=3'));
        self::assertSame(['2026-03-08 04:00 EDT', '2026-03-09 04:00 EDT', '2026-03-10 04:00 EDT'], $ends($event));
        self::assertSame('2026-03-10 08:00', $event->seriesEndsAt()?->format('Y-m-d H:i'));

        $allDay = Event::schedule('Salon', new DateTimeImmutable('2026-03-07', $zone), new DateTimeImmutable('2026-03-08', $zone), true, 'America/Toronto');
        self::assertSame(['2026-03-08 00:00 EST'], $ends($allDay));
    }

    public function testAZoneThatSkipsMidnightKeepsItsDaysAndColumns(): void
    {
        $zone = new DateTimeZone('America/Santiago');
        $period = Period::of(CalendarView::Week, new DateTimeImmutable('2026-09-08', $zone), $zone, 7);
        $days = $period->days();

        self::assertSame(['09-06 01:00', '09-07 00:00', '09-08 00:00'], array_map(static fn (DateTimeImmutable $day): string => $day->format('m-d H:i'), \array_slice($days, 0, 3)));
        self::assertSame('2026-09-13 00:00', $period->until->format('Y-m-d H:i'));

        $item = new Item('a', 'Tôt', new DateTimeImmutable('2026-09-08 00:15', $zone), new DateTimeImmutable('2026-09-08 00:45', $zone), false);
        $grid = TimeGrid::of($period, [$item]);
        self::assertSame([[], [], ['Tôt']], array_map(static fn (TimeColumn $column): array => array_map(static fn (TimedBlock $block): string => $block->item->title, $column->blocks), \array_slice($grid->columns, 0, 3)));
        self::assertSame(2, Lanes::of([new Item('b', 'Jour', new DateTimeImmutable('2026-09-08', $zone), new DateTimeImmutable('2026-09-08', $zone), true)], $days)[0]->column);
        self::assertSame(2, Period::dayNumber(new DateTimeImmutable('1970-01-03 23:00', $zone)));
        self::assertSame(-2, Period::dayNumber(new DateTimeImmutable('1969-12-30', $zone)));
        self::assertSame(20675, Period::dayNumber(new DateTimeImmutable('2026-08-10 23:59', $zone)));
        self::assertSame('09-07 00:00', Period::following($days[0])->format('m-d H:i'));
    }

    public function testBlocksFollowTheHoursOfAShortDay(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $period = Period::of(CalendarView::Day, new DateTimeImmutable('2026-03-08', $zone), $zone);
        $noon = new Item('a', 'Midi', new DateTimeImmutable('2026-03-08 12:00', $zone), new DateTimeImmutable('2026-03-08 13:00', $zone), false);
        $block = TimeGrid::of($period, [$noon])->columns[0]->blocks[0];

        self::assertSame([50.0, 4.1667], [$block->top, $block->height]);

        $hours = TimeGrid::of($period, [$noon], null, 8, 20)->columns[0]->blocks[0];
        self::assertSame([33.3333, 8.3333], [$hours->top, $hours->height]);
    }

    public function testInstantsAtOneTimeStandSideBySide(): void
    {
        $zone = new DateTimeZone('UTC');
        $period = Period::of(CalendarView::Day, new DateTimeImmutable('2026-03-10', $zone), $zone);
        $at = new DateTimeImmutable('2026-03-10 10:00', $zone);
        $blocks = TimeGrid::of($period, [
            new Item('a', 'A', $at, $at, false),
            new Item('b', 'B', $at, $at, false),
            new Item('c', 'C', $at->modify('+29 minutes'), $at->modify('+40 minutes'), false),
            new Item('d', 'D', $at->modify('+30 minutes'), $at->modify('+40 minutes'), false),
        ])->columns[0]->blocks;

        self::assertSame([['A', 0, 3], ['B', 1, 3], ['C', 2, 3], ['D', 0, 3]], array_map(static fn (TimedBlock $block): array => [$block->item->title, $block->lane, $block->lanes], $blocks));
        self::assertSame(0.0, $blocks[0]->height);

        $long = TimeGrid::of($period, [
            new Item('l', 'Long', $at, $at->modify('+2 hours'), false),
            new Item('m', 'Court', $at->modify('+30 minutes'), $at->modify('+45 minutes'), false),
            new Item('n', 'Plus tard', $at->modify('+60 minutes'), $at->modify('+75 minutes'), false),
        ])->columns[0]->blocks;
        self::assertSame([['Long', 0, 2], ['Court', 1, 2], ['Plus tard', 1, 2]], array_map(static fn (TimedBlock $block): array => [$block->item->title, $block->lane, $block->lanes], $long), 'one cluster while the long one lasts');

        $apart = TimeGrid::of($period, [new Item('a', 'A', $at, $at, false), new Item('e', 'E', $at->modify('+31 minutes'), $at->modify('+31 minutes'), false)])->columns[0]->blocks;
        self::assertSame([1, 1], array_map(static fn (TimedBlock $block): int => $block->lanes, $apart));
    }
}
