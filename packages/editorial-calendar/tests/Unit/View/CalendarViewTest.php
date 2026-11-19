<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit\View;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\Contract\TransitionReader;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\EditorialCalendar\Transition\NoTransitions;
use Phil\EditorialCalendar\Transition\PlannedTransition;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\EditorialCalendar\View\Day;
use Phil\EditorialCalendar\View\Entry;
use Phil\EditorialCalendar\View\EntryKind;
use Phil\EditorialCalendar\View\Period;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalendarView::class)]
#[CoversClass(Period::class)]
#[CoversClass(Day::class)]
#[CoversClass(Entry::class)]
#[CoversClass(EntryKind::class)]
#[CoversClass(PlannedTransition::class)]
#[CoversClass(NoTransitions::class)]
final class CalendarViewTest extends TestCase
{
    public function testADayInATimeZone(): void
    {
        $day = new CalendarView()->day($this->calendar(), new DateTimeImmutable('2026-11-16 03:00Z'), new DateTimeZone('America/Toronto'));

        self::assertSame('2026-11-15T05:00:00+00:00', $day->from->format('c'));
        self::assertSame('2026-11-16T05:00:00+00:00', $day->to->format('c'));
        self::assertCount(1, $day->days);
        self::assertSame('2026-11-15', $day->days[0]->date);
        self::assertSame(['late-sunday'], $this->keys($day->entries));
        self::assertSame(['late-sunday'], $this->keys($day->days[0]->entries));
        self::assertFalse($day->isEmpty());
    }

    public function testViewsAreFiltered(): void
    {
        $item = static fn (string $id, string $author, ?string $section, string $at): PlannedItem => PlannedItem::plan($id, ResourceReference::of('article', $id), 'T', 'fr', new AuthorId($author), new DateTimeImmutable($at), $section);
        $calendar = EditorialCalendar::of(
            [
                $item('alone-u', 'u', 'news', '2026-11-16 09:00Z'),
                $item('alone-v', 'v', 'blog', '2026-11-16 10:00Z'),
                $item('wave-u', 'u', 'news', '2026-11-16 11:00Z'),
                $item('wave-v', 'v', 'news', '2026-11-16 11:00Z'),
                $item('other-v', 'v', 'blog', '2026-11-16 12:00Z'),
            ],
            [
                new PublicationBatch('wave', 'Wave', ['wave-u', 'wave-v'], new DateTimeImmutable('2026-11-16 11:00Z')),
                new PublicationBatch('other', 'Other', ['other-v'], new DateTimeImmutable('2026-11-16 12:00Z')),
            ],
        );
        $view = new CalendarView($this->transitions());
        $date = new DateTimeImmutable('2026-11-16 08:00Z');
        $zone = new DateTimeZone('UTC');
        $byU = Filter::all()->byAuthors(new AuthorId('u'));

        self::assertSame(['alone-u', 'alone-v', 'wave', 'other', 'page:9 unpublish'], $this->keys($view->day($calendar, $date, $zone)->entries));

        $day = $view->day($calendar, $date, $zone, $byU);
        self::assertSame(['alone-u', 'wave'], $this->keys($day->entries));
        self::assertSame(['wave-u'], array_map(static fn (PlannedItem $planned): string => $planned->id, $day->entries[1]->items));
        self::assertSame(['alone-u', 'wave'], $this->keys($day->days[0]->entries));

        self::assertSame(['alone-u', 'alone-v', 'wave', 'other'], $this->keys($view->week($calendar, $date, $zone, 1, Filter::all()->byStates(ItemState::Idea)->bySections('news', 'blog'))->entries));
        self::assertSame(['alone-v', 'other'], $this->keys($view->month($calendar, $date, $zone, Filter::all()->bySections('blog'))->entries));
        self::assertSame(['wave'], $this->keys($view->list($calendar, $date, $date->modify('+1 day'), Filter::all()->bySections('news')->byAuthors(new AuthorId('v')))->entries));
        self::assertSame([], $view->list($calendar, $date, $date->modify('+1 day'), Filter::all()->byStates(ItemState::Ready))->entries);
        self::assertCount(5, $view->list($calendar, $date, $date->modify('+1 day'), Filter::all())->entries);
    }

    public function testAWeekStartsOnTheDayAsked(): void
    {
        $view = new CalendarView();
        $zone = new DateTimeZone('UTC');

        $monday = $view->week($this->calendar(), new DateTimeImmutable('2026-11-18 15:00Z'), $zone);
        self::assertSame(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22'], array_map(static fn (Day $day): string => $day->date, $monday->days));
        self::assertSame('2026-11-16T00:00:00+00:00', $monday->from->format('c'));
        self::assertSame('2026-11-23T00:00:00+00:00', $monday->to->format('c'));

        $sunday = $view->week($this->calendar(), new DateTimeImmutable('2026-11-18Z'), $zone, 7);
        self::assertSame('2026-11-15', $sunday->days[0]->date);
        self::assertSame('2026-11-21', $sunday->days[6]->date);

        $sameDay = $view->week($this->calendar(), new DateTimeImmutable('2026-11-18Z'), $zone, 3);
        self::assertSame('2026-11-18', $sameDay->days[0]->date);

        $thursday = $view->week($this->calendar(), new DateTimeImmutable('2026-11-18Z'), $zone, 4);
        self::assertSame('2026-11-12', $thursday->days[0]->date);

        $onSunday = $view->week($this->calendar(), new DateTimeImmutable('2026-11-22Z'), $zone);
        self::assertSame('2026-11-16', $onSunday->days[0]->date);
    }

    public function testAWeekStartsOnADayNumberedOneToSeven(): void
    {
        foreach ([0, 8] as $firstDay) {
            try {
                new CalendarView()->week($this->calendar(), new DateTimeImmutable(), new DateTimeZone('UTC'), $firstDay);
                self::fail('A week started on day '.$firstDay.'.');
            } catch (InvalidItem $refused) {
                self::assertStringContainsString($firstDay.' is not one', $refused->getMessage());
            }
        }
    }

    public function testAMonthHasItsDaysAndEntriesDayByDay(): void
    {
        $month = new CalendarView($this->transitions())->month($this->calendar(), new DateTimeImmutable('2026-11-18Z'), new DateTimeZone('America/Toronto'));

        self::assertCount(30, $month->days);
        self::assertSame('2026-11-01', $month->days[0]->date);
        self::assertSame('2026-11-30', $month->days[29]->date);
        self::assertSame('2026-11-01T04:00:00+00:00', $month->from->format('c'));
        self::assertSame('2026-12-01T05:00:00+00:00', $month->to->format('c'));
        self::assertSame(['late-sunday', 'wave', 'a', 'page:9 unpublish', 'b'], $this->keys($month->entries));
        self::assertSame(['wave', 'a', 'page:9 unpublish'], $this->keys($month->days[15]->entries));
        self::assertSame(['b'], $this->keys($month->days[16]->entries));
        self::assertCount(28, new CalendarView()->month(EditorialCalendar::of(), new DateTimeImmutable('2027-02-10Z'), new DateTimeZone('UTC'))->days);
    }

    public function testADayAcrossAChangeOfTimeLasts25Hours(): void
    {
        $month = new CalendarView()->month(EditorialCalendar::of(), new DateTimeImmutable('2026-11-01 12:00Z'), new DateTimeZone('America/Toronto'));
        $first = $month->days[0];

        self::assertSame(25 * 3600, $first->to->getTimestamp() - $first->from->getTimestamp());
        self::assertSame('2026-11-02T05:00:00+00:00', $month->days[1]->from->format('c'));
    }

    public function testABatchIsOneEntryWithItsItems(): void
    {
        $period = new CalendarView($this->transitions())->list($this->calendar(), new DateTimeImmutable('2026-11-16 12:00Z'), new DateTimeImmutable('2026-11-17Z'));
        $batch = $period->entries[0];

        self::assertSame(EntryKind::Batch, $batch->kind);
        self::assertSame('wave', $batch->batch?->id);
        self::assertSame(['w1', 'w2'], array_map(static fn (PlannedItem $item): string => $item->id, $batch->items));
        self::assertSame('2026-11-16T14:00:00+00:00', $batch->at->format('c'));
        self::assertNull($batch->transition);

        $item = $period->entries[1];
        self::assertSame(EntryKind::Item, $item->kind);
        self::assertSame(['a'], array_map(static fn (PlannedItem $item): string => $item->id, $item->items));
        self::assertNull($item->batch);

        $transition = $period->entries[2];
        self::assertSame(EntryKind::Transition, $transition->kind);
        self::assertSame([], $transition->items);
        self::assertSame('unpublish', $transition->transition?->transition);
        self::assertSame([], $period->days);
    }

    public function testWithdrawnItemsAreLeftOut(): void
    {
        $calendar = $this->calendar()->transition('a', ItemState::Withdrawn)->transition('w1', ItemState::Withdrawn);
        $period = new CalendarView($this->transitions())->list($calendar, new DateTimeImmutable('2026-11-16 12:00Z'), new DateTimeImmutable('2026-11-17Z'));

        self::assertSame(['wave', 'page:9 unpublish'], $this->keys($period->entries));
        self::assertSame(['w2'], array_map(static fn (PlannedItem $item): string => $item->id, $period->entries[0]->items));

        $empty = $calendar->transition('w2', ItemState::Withdrawn);
        self::assertSame(['page:9 unpublish'], $this->keys(new CalendarView($this->transitions())->list($empty, new DateTimeImmutable('2026-11-16 12:00Z'), new DateTimeImmutable('2026-11-17Z'))->entries));
    }

    public function testPublishedItemsStay(): void
    {
        $calendar = $this->calendar()->transition('a', ItemState::Drafting)->transition('a', ItemState::Ready)->transition('a', ItemState::Published);

        self::assertContains('a', $this->keys(new CalendarView()->list($calendar, new DateTimeImmutable('2026-11-16 12:00Z'), new DateTimeImmutable('2026-11-17Z'))->entries));
    }

    public function testAListIncludesItsStartAndExcludesItsEnd(): void
    {
        $view = new CalendarView($this->transitions());
        $calendar = $this->calendar();

        self::assertSame(['wave', 'a', 'page:9 unpublish'], $this->keys($view->list($calendar, new DateTimeImmutable('2026-11-16 14:00Z'), new DateTimeImmutable('2026-11-16 14:00:01Z'))->entries));
        self::assertSame([], $view->list($calendar, new DateTimeImmutable('2026-11-16 13:00Z'), new DateTimeImmutable('2026-11-16 14:00Z'))->entries);
        self::assertTrue($view->list($calendar, new DateTimeImmutable('2026-11-16 13:00Z'), new DateTimeImmutable('2026-11-16 14:00Z'))->isEmpty());
        self::assertSame('2026-11-16T13:00:00+00:00', $view->list($calendar, new DateTimeImmutable('2026-11-16 14:00+01:00'), new DateTimeImmutable('2026-11-16 15:00+01:00'))->from->format('c'));
        self::assertSame('2026-11-16T14:00:00+00:00', $view->list($calendar, new DateTimeImmutable('2026-11-16 14:00+01:00'), new DateTimeImmutable('2026-11-16 15:00+01:00'))->to->format('c'));
    }

    public function testATransitionOutsideTheRangeIsLeftOutWhateverTheReaderGives(): void
    {
        $reader = new class implements TransitionReader {
            public function between(DateTimeImmutable $from, DateTimeImmutable $to): iterable
            {
                yield new PlannedTransition(ResourceReference::of('page', '1'), 'open', $from->modify('-1 second'));
                yield new PlannedTransition(ResourceReference::of('page', '2'), 'open', $to);
                yield new PlannedTransition(ResourceReference::of('page', '3'), 'open', $from);
            }
        };

        self::assertSame(['page:3 open'], $this->keys(new CalendarView($reader)->list(EditorialCalendar::of(), new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-17Z'))->entries));
    }

    public function testAListEndsAfterItStarts(): void
    {
        $this->expectException(InvalidItem::class);
        new CalendarView()->list($this->calendar(), new DateTimeImmutable('2026-11-16Z'), new DateTimeImmutable('2026-11-16Z'));
    }

    public function testEntriesOfOneMomentAreOrderedByKindThenKey(): void
    {
        $at = new DateTimeImmutable('2026-11-16Z');
        $item = PlannedItem::plan('b', ResourceReference::of('a', '1'), 'T', 'fr', new AuthorId('u'), $at);
        $other = PlannedItem::plan('a', ResourceReference::of('a', '2'), 'T', 'fr', new AuthorId('u'), $at);
        $batch = Entry::batch(new PublicationBatch('z', 'Z', ['b']), $at, [$item]);
        $transition = Entry::transition(new PlannedTransition(ResourceReference::of('page', '1'), 'open', $at));

        self::assertSame(-1, Entry::compare($batch, Entry::item($item, $at)));
        self::assertSame(-1, Entry::compare(Entry::item($item, $at), $transition));
        self::assertSame(1, Entry::compare(Entry::item($item, $at), Entry::item($other, $at)));
        self::assertSame(-1, Entry::compare($transition, Entry::item($item, $at->modify('+1 second'))));
        self::assertSame(0, Entry::compare(Entry::item($item, $at), Entry::item($item, new DateTimeImmutable('2026-11-16 01:00+01:00'))));
        self::assertSame([0, 1, 2], [EntryKind::Batch->rank(), EntryKind::Item->rank(), EntryKind::Transition->rank()]);
    }

    public function testATransitionIsKeptInUtc(): void
    {
        $transition = new PlannedTransition(ResourceReference::of('page', '1'), 'open', new DateTimeImmutable('2026-11-16 01:00+01:00'));

        self::assertSame('2026-11-16T00:00:00+00:00', $transition->at->format('c'));
        self::assertSame([], new NoTransitions()->between(new DateTimeImmutable(), new DateTimeImmutable()));
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function keys(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->key, $entries);
    }

    private function calendar(): EditorialCalendar
    {
        $item = static fn (string $id, ?string $at = null): PlannedItem => PlannedItem::plan($id, ResourceReference::of('article', $id), 'Title '.$id, 'fr', new AuthorId('u'), null === $at ? null : new DateTimeImmutable($at));

        return EditorialCalendar::of(
            [
                $item('a', '2026-11-16 14:00Z'),
                $item('b', '2026-11-17 14:00Z'),
                $item('late-sunday', '2026-11-16 03:00Z'),
                $item('w1', '2026-12-25Z'),
                $item('w2'),
                $item('m1', '2026-11-20Z'),
                $item('idea'),
            ],
            [
                new PublicationBatch('wave', 'Wave', ['w1', 'w2'], new DateTimeImmutable('2026-11-16 14:00Z')),
                new PublicationBatch('manual', 'Manual', ['m1']),
            ],
        );
    }

    private function transitions(): TransitionReader
    {
        return new class implements TransitionReader {
            public function between(DateTimeImmutable $from, DateTimeImmutable $to): iterable
            {
                return [new PlannedTransition(ResourceReference::of('page', '9'), 'unpublish', new DateTimeImmutable('2026-11-16 14:00Z'))];
            }
        };
    }
}
