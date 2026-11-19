<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use InvalidArgumentException;
use Phil\CalendarBundle\Application\View\Agenda;
use Phil\CalendarBundle\Application\View\AgendaGroup;
use Phil\CalendarBundle\Application\View\Bar;
use Phil\CalendarBundle\Application\View\CalendarResource;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Lanes;
use Phil\CalendarBundle\Application\View\MonthGrid;
use Phil\CalendarBundle\Application\View\MonthWeek;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Application\View\Resources;
use Phil\CalendarBundle\Application\View\TimeColumn;
use Phil\CalendarBundle\Application\View\TimedBlock;
use Phil\CalendarBundle\Application\View\TimeGrid;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MonthGrid::class)]
#[CoversClass(MonthWeek::class)]
#[CoversClass(Lanes::class)]
#[CoversClass(Bar::class)]
#[CoversClass(TimeGrid::class)]
#[CoversClass(TimeColumn::class)]
#[CoversClass(TimedBlock::class)]
#[CoversClass(Resources::class)]
#[CoversClass(CalendarResource::class)]
#[CoversClass(Agenda::class)]
#[CoversClass(AgendaGroup::class)]
#[CoversClass(Item::class)]
final class LayoutsTest extends ViewTestCase
{
    /**
     * Bars across their days, split at the end of a week, each in the first lane free, the longest first.
     */
    public function testAMonth(): void
    {
        $grid = MonthGrid::of(Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone), [
            $this->days('Festival', '2026-09-04', '2026-09-08'),
            $this->days('Fair', '2026-09-05'),
            $this->at('Talk', '2026-09-05 19:00', '2026-09-05 21:00'),
            $this->at('Night', '2026-09-06 22:00', '2026-09-07 02:00'),
            $this->days('Outside', '2026-10-10'),
        ]);

        self::assertCount(5, $grid->weeks);
        self::assertSame(['2026-08-31', '2026-09-06'], [$grid->weeks[0]->days[0]->format('Y-m-d'), $grid->weeks[0]->days[6]->format('Y-m-d')]);
        self::assertSame([['Festival', 4, 3, 0, false, true], ['Fair', 5, 1, 1, false, false], ['Talk', 5, 1, 2, false, false], ['Night', 6, 1, 1, false, true]], $this->bars($grid->weeks[0]->bars));
        self::assertSame([['Festival', 0, 2, 0, true, false], ['Night', 0, 1, 1, true, false]], $this->bars($grid->weeks[1]->bars));
        self::assertSame([[], 3, 2, 0], [$grid->weeks[0]->hidden, $grid->weeks[0]->lanes(), $grid->weeks[1]->lanes(), $grid->weeks[2]->lanes()]);
        self::assertSame([['Fair', 5, 1, 1, false, false], ['Night', 6, 1, 1, false, true]], $this->bars($grid->weeks[0]->lane(1)));
        self::assertSame('2026-09-15', $grid->period->date->format('Y-m-d'));
    }

    /**
     * Past the lanes shown, an item counts in each of its days.
     */
    public function testWhatDoesNotFit(): void
    {
        $grid = MonthGrid::of(Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone), [
            $this->days('A', '2026-09-14', '2026-09-16'),
            $this->days('B', '2026-09-15'),
            $this->days('C', '2026-09-15', '2026-09-16'),
        ], 1);

        self::assertSame([['A', 0, 3, 0, false, false]], $this->bars($grid->weeks[2]->bars));
        self::assertSame([1 => ['C', 'B'], 2 => ['C']], array_map(static fn (array $items): array => array_map(static fn (Item $item): string => $item->title, $items), $grid->weeks[2]->hidden));
        self::assertCount(2, MonthGrid::of(Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone), [$this->days('A', '2026-09-14', '2026-09-16'), $this->days('B', '2026-09-15'), $this->days('C', '2026-09-15', '2026-09-16')], 2)->weeks[2]->bars);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A month shows at least one lane, not 0.');
        MonthGrid::of(Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone), [], 0);
    }

    /**
     * Times in their columns, side by side where they overlap; whole days across the week on top.
     */
    public function testAWeek(): void
    {
        $grid = TimeGrid::of(Period::of(CalendarView::Week, $this->day('2026-09-15'), $this->zone), [
            $this->days('Fair', '2026-09-13', '2026-09-15'),
            $this->at('A', '2026-09-15 09:00', '2026-09-15 11:00'),
            $this->at('B', '2026-09-15 10:00', '2026-09-15 12:00'),
            $this->at('C', '2026-09-15 11:30', '2026-09-15 12:30'),
            $this->at('D', '2026-09-15 13:00', '2026-09-15 14:00'),
            $this->at('Night', '2026-09-16 22:00', '2026-09-17 02:00'),
        ], firstHour: 8, lastHour: 20);

        self::assertCount(7, $grid->columns);
        self::assertSame(range(8, 19), $grid->hours());
        self::assertSame([['Fair', 0, 2, 0, true, false]], $this->bars($grid->allDay));
        self::assertSame([], $grid->columns[0]->blocks, 'a whole day is no time');
        self::assertSame([['A', 8.3333, 16.6667, 0, 2, false, false], ['B', 16.6667, 16.6667, 1, 2, false, false], ['C', 29.1667, 8.3333, 0, 2, false, false], ['D', 41.6667, 8.3333, 0, 1, false, false]], $this->blocks($grid->columns[1]->blocks));
        self::assertSame([['Night', 100.0, 0.0, 0, 1, false, true]], $this->blocks($grid->columns[2]->blocks), 'after the hours shown: pinned to their end');
        self::assertSame([['Night', 0.0, 0.0, 0, 1, true, false]], $this->blocks($grid->columns[3]->blocks), 'before them: pinned to their start');
        self::assertSame([[], null], [$grid->columns[1]->allDay, $grid->columns[1]->resource]);
        self::assertSame('2026-09-15', $grid->columns[1]->day->format('Y-m-d'));
        self::assertNull($grid->resources);
    }

    /**
     * The order of a row: the earliest first, then the longest, whole days before times, then by time and title.
     */
    public function testTheOrderOfALane(): void
    {
        $week = Period::of(CalendarView::Week, $this->day('2026-09-15'), $this->zone);

        self::assertSame([['Z long', 0, 3, 0, false, false], ['A short', 0, 1, 1, false, false]], $this->bars(Lanes::of([$this->days('A short', '2026-09-14'), $this->days('Z long', '2026-09-14', '2026-09-16')], $week->days())));
        self::assertSame([['Z all day', 1, 1, 0, false, false], ['A timed', 1, 1, 1, false, false]], $this->bars(Lanes::of([$this->at('A timed', '2026-09-15 09:00', '2026-09-15 10:00'), $this->days('Z all day', '2026-09-15')], $week->days())));
        self::assertSame([['Fair', 5, 2, 0, false, false]], $this->bars(Lanes::of([$this->days('Fair', '2026-09-19', '2026-09-20')], $week->days())), 'ending on the last day: nothing after');
        self::assertSame([['A', 0, 2, 0, false, false], ['B', 1, 2, 1, false, false], ['C', 3, 1, 0, false, false]], $this->bars(Lanes::of([$this->days('A', '2026-09-14', '2026-09-15'), $this->days('B', '2026-09-15', '2026-09-16'), $this->days('C', '2026-09-17')], $week->days())), 'a lane taken to the column where the next starts');
        self::assertSame([['Z long', 0, 3, 0, false, false], ['A mid', 0, 2, 1, false, false], ['B', 2, 1, 1, false, false]], $this->bars(Lanes::of([$this->days('A mid', '2026-09-14', '2026-09-15'), $this->days('Z long', '2026-09-14', '2026-09-16'), $this->days('B', '2026-09-16')], $week->days())));
        self::assertSame([['Early', 0, 1, 0, false, false], ['Late', 0, 1, 1, false, false]], $this->bars(Lanes::of([$this->days('Late', '2026-09-14'), $this->days('Early', '2026-09-14')], [$this->day('2026-09-14')])), 'then by title');
    }

    /**
     * The default lanes of a month, three; what does not fit, by column; the bars in a list.
     */
    public function testTheLanesOfAMonth(): void
    {
        $period = Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone);
        $grid = MonthGrid::of($period, [
            $this->days('A', '2026-09-15'), $this->days('B', '2026-09-15'), $this->days('C', '2026-09-15'),
            $this->days('D', '2026-09-16', '2026-09-17'), $this->days('E', '2026-09-16'), $this->days('F', '2026-09-16'), $this->days('G', '2026-09-16'),
            $this->days('H', '2026-09-15'),
        ]);

        self::assertSame([1 => ['H'], 2 => ['G']], array_map(static fn (array $items): array => array_map(static fn (Item $item): string => $item->title, $items), $grid->weeks[2]->hidden));
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F'], array_map(static fn (Bar $bar): string => $bar->item->title, $grid->weeks[2]->bars));
        self::assertSame(range(0, 5), array_keys($grid->weeks[2]->bars));

        $late = MonthGrid::of($period, [$this->days('Long', '2026-09-15', '2026-09-18'), $this->days('X', '2026-09-15'), $this->days('Y', '2026-09-14', '2026-09-15'), $this->days('Z', '2026-09-16')], 1);
        self::assertSame([1, 2, 3, 4], array_keys($late->weeks[2]->hidden), 'by column');
    }

    public function testADayAndItsInstants(): void
    {
        $grid = TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-26'), $this->zone), [
            new Item('moon', 'Full moon', $this->day('2026-09-26 12:48'), $this->day('2026-09-26 12:48'), false),
            new Item('before', 'Before', $this->day('2026-09-25 23:00'), $this->day('2026-09-26 00:00'), false),
            $this->at('All', '2026-09-25 20:00', '2026-09-27 08:00'),
        ]);

        self::assertSame([['All', 0.0, 100.0, 0, 2, true, true], ['Full moon', 53.3333, 0.0, 1, 2, false, false]], $this->blocks($grid->columns[0]->blocks));

        $edges = TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-26'), $this->zone), [
            new Item('m', 'Midnight moon', $this->day('2026-09-26 00:00'), $this->day('2026-09-26 00:00'), false),
            new Item('y', 'Yesterday moon', $this->day('2026-09-25 12:00'), $this->day('2026-09-25 12:00'), false),
            $this->at('Midnight', '2026-09-26 00:00', '2026-09-26 01:00'),
            $this->at('Last hour', '2026-09-26 23:00', '2026-09-27 00:00'),
            $this->at('Tomorrow', '2026-09-27 00:00', '2026-09-27 01:00'),
        ]);
        self::assertSame([['Midnight', 0.0, 4.1667, 0, 2, false, false], ['Midnight moon', 0.0, 0.0, 1, 2, false, false], ['Last hour', 95.8333, 4.1667, 0, 1, false, false]], $this->blocks($edges->columns[0]->blocks));
        self::assertSame([0, 24], [$grid->firstHour, $grid->lastHour]);
    }

    /**
     * Side by side: the longest first at the same time, a lane reused once free, groups that only touch kept apart,
     * and a group held open by its longest item.
     */
    public function testOverlaps(): void
    {
        $blocks = fn (Item ...$items): array => $this->blocks(TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-15'), $this->zone), array_values($items), firstHour: 8, lastHour: 20)->columns[0]->blocks);

        self::assertSame([['Z long', 16.6667, 16.6667, 0, 2, false, false], ['A short', 16.6667, 8.3333, 1, 2, false, false]], $blocks($this->at('A short', '2026-09-15 10:00', '2026-09-15 11:00'), $this->at('Z long', '2026-09-15 10:00', '2026-09-15 12:00')));
        self::assertSame([['X', 8.3333, 25.0, 0, 2, false, false], ['A', 8.3333, 8.3333, 1, 2, false, false], ['B', 16.6667, 8.3333, 1, 2, false, false]], $blocks($this->at('X', '2026-09-15 09:00', '2026-09-15 12:00'), $this->at('A', '2026-09-15 09:00', '2026-09-15 10:00'), $this->at('B', '2026-09-15 10:00', '2026-09-15 11:00')));
        self::assertSame([['A', 8.3333, 8.3333, 0, 2, false, false], ['C', 8.3333, 8.3333, 1, 2, false, false], ['B', 16.6667, 8.3333, 0, 1, false, false]], $blocks($this->at('A', '2026-09-15 09:00', '2026-09-15 10:00'), $this->at('C', '2026-09-15 09:00', '2026-09-15 10:00'), $this->at('B', '2026-09-15 10:00', '2026-09-15 11:00')));
        self::assertSame([['X', 8.3333, 25.0, 0, 2, false, false], ['A', 8.3333, 8.3333, 1, 2, false, false], ['B', 25.0, 8.3333, 1, 2, false, false]], $blocks($this->at('X', '2026-09-15 09:00', '2026-09-15 12:00'), $this->at('A', '2026-09-15 09:00', '2026-09-15 10:00'), $this->at('B', '2026-09-15 11:00', '2026-09-15 12:00')));
    }

    /**
     * A column per day and resource; an item in each of its resources, or in the other column.
     */
    public function testSharedDays(): void
    {
        $items = [
            $this->at('Game', '2026-09-15 10:00', '2026-09-15 12:00', hosts: ['shop:1', 'club:2']),
            $this->days('Fair', '2026-09-15', '2026-09-16', hosts: ['shop:1']),
            $this->days('Market', '2026-09-15', hosts: ['club:2']),
            $this->at('Lone', '2026-09-15 13:00', '2026-09-15 14:00'),
        ];
        $hosts = TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-15'), $this->zone), $items, Resources::hosts(['shop:1' => 'The shop', 'club:2' => 'The club'], 'Others'));

        self::assertSame([['The shop', ['Fair'], ['Game']], ['The club', ['Market'], ['Game']], ['Others', [], ['Lone']]], $this->columns($hosts->columns));
        self::assertSame([0], array_keys($hosts->columns[0]->allDay));
        self::assertSame([[], ''], [$hosts->allDay, $hosts->columns[2]->resource?->key]);
        self::assertSame([['The shop', ['Fair'], ['Game']], ['The club', ['Market'], ['Game']]], $this->columns(TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-15'), $this->zone), $items, Resources::hosts(['shop:1' => 'The shop', 'club:2' => 'The club']))->columns), 'without the other column, the item alone is not shown');

        $venues = Resources::venues(['Bar' => 'The bar'], 'Elsewhere');
        self::assertSame(['The bar'], array_map(static fn (CalendarResource $resource): string => $resource->label, $venues->of($this->days('X', '2026-09-15', venue: 'Bar'))));
        self::assertSame(['Elsewhere'], array_map(static fn (CalendarResource $resource): string => $resource->label, $venues->of($this->days('X', '2026-09-15'))));
        self::assertSame(['Elsewhere'], array_map(static fn (CalendarResource $resource): string => $resource->label, $venues->of($this->days('X', '2026-09-15', venue: 'Park'))));

        $tags = Resources::tags(['larp' => 'GN', 'rpg' => 'JdR']);
        self::assertSame(['GN', 'JdR'], array_map(static fn (CalendarResource $resource): string => $resource->label, $tags->of($this->days('X', '2026-09-15', tags: ['rpg', 'larp']))));
        self::assertSame(['JdR'], array_map(static fn (CalendarResource $resource): string => $resource->label, $tags->of($this->days('X', '2026-09-15', tags: ['rpg']))), 'a list, whatever the columns before');
        self::assertSame(['larp', 'rpg'], array_map(static fn (CalendarResource $resource): string => $resource->key, $tags->columns()));
        self::assertSame([], Resources::tags([1 => 'One'])->of($this->days('X', '2026-09-15', tags: ['2'])));
        self::assertSame('1', Resources::tags([1 => 'One'])->columns()[0]->key);
        self::assertSame(2, \count(TimeGrid::of(Period::of(CalendarView::Week, $this->day('2026-09-15'), $this->zone), [], $tags)->columns) / 7);

        $week = TimeGrid::of(Period::of(CalendarView::Week, $this->day('2026-09-15'), $this->zone), [
            $this->days('Fair', '2026-09-15', '2026-09-16', hosts: ['shop:1']),
            $this->days('Sale', '2026-09-16', '2026-09-17', hosts: ['shop:1']),
        ], Resources::hosts(['shop:1' => 'The shop']));
        self::assertSame([[], ['Fair'], ['Fair', 'Sale'], ['Sale'], [], [], []], array_map(static fn (TimeColumn $column): array => array_map(static fn (Item $item): string => $item->title, $column->allDay), $week->columns), 'a whole day in the columns of its days');
        self::assertSame([0], array_keys($week->columns[3]->allDay));
    }

    public function testTheHoursShown(): void
    {
        foreach ([[-1, 24], [0, 25], [10, 10], [12, 8]] as [$first, $last]) {
            try {
                TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-15'), $this->zone), [], firstHour: $first, lastHour: $last);
                self::fail(\sprintf('%d to %d', $first, $last));
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('The hours shown run from 0 to 24, the first before the last; not %d to %d.', $first, $last), $invalidArgumentException->getMessage());
            }
        }

        self::assertSame(range(23, 23), TimeGrid::of(Period::of(CalendarView::Day, $this->day('2026-09-15'), $this->zone), [], firstHour: 23, lastHour: 24)->hours());
    }

    /**
     * Groups by first day, then last day, the shortest first, as the list shows them.
     */
    public function testAnAgenda(): void
    {
        $agenda = Agenda::of([
            $this->days('Event 9', '2026-09-04'),
            $this->days('Week', '2026-09-01', '2026-09-05'),
            $this->days('Z midnight all day', '2026-09-03'),
            $this->at('A midnight', '2026-09-03 00:00', '2026-09-03 01:00'),
            $this->days('Event 7', '2026-09-02', '2026-09-04'),
            $this->days('Event 3', '2026-09-01', '2026-09-02'),
            $this->at('Event 2', '2026-09-01 19:00', '2026-09-01 21:00'),
            $this->days('Event 1', '2026-09-01'),
            $this->days('Event 5', '2026-09-02', '2026-09-03'),
            $this->days('Event 4', '2026-09-02'),
            $this->days('Event 8', '2026-09-03', '2026-09-04'),
            $this->days('Event 6', '2026-09-02', '2026-09-03'),
            $this->at('Late', '2026-09-04 22:00', '2026-09-05 00:00'),
        ]);

        self::assertSame([
            ['2026-09-01', '2026-09-01', ['Event 1', 'Event 2']],
            ['2026-09-01', '2026-09-02', ['Event 3']],
            ['2026-09-01', '2026-09-05', ['Week']],
            ['2026-09-02', '2026-09-02', ['Event 4']],
            ['2026-09-02', '2026-09-03', ['Event 5', 'Event 6']],
            ['2026-09-02', '2026-09-04', ['Event 7']],
            ['2026-09-03', '2026-09-03', ['Z midnight all day', 'A midnight']],
            ['2026-09-03', '2026-09-04', ['Event 8']],
            ['2026-09-04', '2026-09-04', ['Event 9', 'Late']],
        ], array_map(static fn (AgendaGroup $group): array => [$group->first->format('Y-m-d'), $group->last->format('Y-m-d'), array_map(static fn (Item $item): string => $item->title, $group->items)], $agenda->groups));
        self::assertSame(['Event 1', 'Event 2', 'Event 3', 'Week'], \array_slice(array_map(static fn (Item $item): string => $item->title, $agenda->items()), 0, 4));
        self::assertSame([], Agenda::of([])->items());
    }

    /**
     * The days an item covers: a time that ends at midnight ends the day before; an instant is its day.
     */
    public function testTheDaysOfAnItem(): void
    {
        $night = $this->at('Night', '2026-09-04 22:00', '2026-09-05 00:00');
        $instant = new Item('m', 'Moon', $this->day('2026-09-26 00:00'), $this->day('2026-09-26 00:00'), false);
        $overnight = $this->at('Over', '2026-09-04 22:00', '2026-09-05 00:30');

        self::assertSame(['2026-09-04', '2026-09-04', false], [$night->firstDay()->format('Y-m-d'), $night->lastDay()->format('Y-m-d'), $night->spansDays()]);
        self::assertSame(['2026-09-26', false], [$instant->lastDay()->format('Y-m-d'), $instant->spansDays()]);
        self::assertSame(['2026-09-05', true], [$overnight->lastDay()->format('Y-m-d'), $overnight->spansDays()]);
        self::assertSame(['2026-09-05', true], [$this->days('X', '2026-09-04', '2026-09-05')->lastDay()->format('Y-m-d'), $this->days('X', '2026-09-04', '2026-09-05')->spansDays()]);
        self::assertSame([Item::EVENT, null, [], [], null, null, null, false], [$night->source, $night->url, $night->tags, $night->hosts, $night->venue, $night->image, $night->description, $night->cancelled]);
        self::assertSame(['event', 'almanac'], [Item::EVENT, Item::ALMANAC]);
        self::assertSame([20700, 20700], [$night->firstDayNumber(), $night->lastDayNumber()], 'the numbers of its days');
        self::assertSame([20700, 20701], [$overnight->firstDayNumber(), $overnight->lastDayNumber()]);
    }

    /**
     * Each item is given to the weeks it covers only: one longer than the grid in each week, one before or after it in
     * none.
     */
    public function testTheWeeksOfAMonthHoldTheirItems(): void
    {
        $grid = MonthGrid::of(Period::of(CalendarView::Month, $this->day('2026-09-15'), $this->zone), [
            $this->days('Season', '2026-08-01', '2026-12-31'),
            $this->days('Before', '2026-08-20', '2026-08-30'),
            $this->days('Edge', '2026-08-24', '2026-08-31'),
            $this->days('After', '2026-10-05', '2026-10-20'),
            $this->days('Last', '2026-10-04'),
        ]);

        self::assertSame([['Season', 0, 7, 0, true, true], ['Edge', 0, 1, 1, true, false]], $this->bars($grid->weeks[0]->bars));
        self::assertSame([['Season', 0, 7, 0, true, true]], $this->bars($grid->weeks[2]->bars));
        self::assertSame([['Season', 0, 7, 0, true, true], ['Last', 6, 1, 1, false, false]], $this->bars($grid->weeks[4]->bars));
        self::assertSame([2, 1, 1, 1, 2], array_map(static fn (MonthWeek $week): int => \count($week->bars), $grid->weeks));
    }

    /**
     * @param list<Bar> $bars
     *
     * @return list<array{string, int, int, int, bool, bool}>
     */
    private function bars(array $bars): array
    {
        return array_map(static fn (Bar $bar): array => [$bar->item->title, $bar->column, $bar->span, $bar->lane, $bar->continuesBefore, $bar->continuesAfter], $bars);
    }

    /**
     * @param list<TimedBlock> $blocks
     *
     * @return list<array{string, float, float, int, int, bool, bool}>
     */
    private function blocks(array $blocks): array
    {
        return array_map(static fn (TimedBlock $block): array => [$block->item->title, $block->top, $block->height, $block->lane, $block->lanes, $block->continuesBefore, $block->continuesAfter], $blocks);
    }

    /**
     * @param list<TimeColumn> $columns
     *
     * @return list<array{string, list<string>, list<string>}>
     */
    private function columns(array $columns): array
    {
        return array_map(static fn (TimeColumn $column): array => [
            (string) $column->resource?->label,
            array_map(static fn (Item $item): string => $item->title, $column->allDay),
            array_map(static fn (TimedBlock $block): string => $block->item->title, $column->blocks),
        ], $columns);
    }
}
