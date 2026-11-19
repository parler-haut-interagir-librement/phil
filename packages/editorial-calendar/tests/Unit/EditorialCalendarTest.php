<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Tests\Unit;

use DateTimeImmutable;
use Phil\EditorialCalendar\AuthorId;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\BatchFailed;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Exception\InvalidTransition;
use Phil\EditorialCalendar\Exception\UnknownItem;
use Phil\EditorialCalendar\Filter;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\PublicationBatch;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditorialCalendar::class)]
#[CoversClass(BatchFailed::class)]
#[CoversClass(UnknownItem::class)]
#[CoversClass(InvalidItem::class)]
#[CoversClass(InvalidTransition::class)]
final class EditorialCalendarTest extends TestCase
{
    public function testItemsAndBatchesAreKeptByIdentifier(): void
    {
        $calendar = $this->calendar();

        self::assertSame(['a', 'b', 'c', '42'], array_map(static fn (PlannedItem $item): string => $item->id, $calendar->items()));
        self::assertSame('B', $calendar->item('b')->title);
        self::assertSame('42', $calendar->item('42')->id);
        self::assertSame(['wave'], array_map(static fn (PublicationBatch $batch): string => $batch->id, $calendar->batches()));
        self::assertSame('wave', $calendar->batch('wave')->id);
        self::assertSame('wave', $calendar->batchOf('b')?->id);
        self::assertNull($calendar->batchOf('a'));
        self::assertSame([], EditorialCalendar::of()->items());
        self::assertSame([], EditorialCalendar::of()->batches());
    }

    public function testAnUnknownItemOrBatchThrows(): void
    {
        try {
            $this->calendar()->item('x');
            self::fail('An unknown item was found.');
        } catch (UnknownItem $unknownItem) {
            self::assertSame('The calendar holds no item "x".', $unknownItem->getMessage());
        }

        $this->expectException(UnknownItem::class);
        $this->expectExceptionMessageIsOrContains('The calendar holds no batch "x".');
        $this->calendar()->batch('x');
    }

    public function testAnIdentifierIsInTheCalendarOnce(): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('The item "a" is in the calendar twice.');
        EditorialCalendar::of([$this->item('a'), $this->item('a')]);
    }

    public function testABatchIdentifierIsInTheCalendarOnce(): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('The batch "w" is in the calendar twice.');
        EditorialCalendar::of([$this->item('a'), $this->item('b')], [new PublicationBatch('w', 'W', ['a']), new PublicationBatch('w', 'W', ['b'])]);
    }

    public function testABatchHoldsOnlyItemsOfTheCalendar(): void
    {
        $this->expectException(UnknownItem::class);
        EditorialCalendar::of([$this->item('a')], [new PublicationBatch('w', 'W', ['a', 'z'])]);
    }

    public function testAnItemIsInOneBatchAtMost(): void
    {
        $calendar = EditorialCalendar::of([$this->item('a'), $this->item('b')], [new PublicationBatch('w', 'W', ['a'])]);

        self::assertSame(['a', 'b'], $calendar->withBatch(new PublicationBatch('w', 'W', ['a', 'b']))->batch('w')->items);

        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('The item "a" is already in the batch "w".');
        $calendar->withBatch(new PublicationBatch('v', 'V', ['b', 'a']));
    }

    public function testACalendarIsNotBuiltWithAnItemInTwoBatches(): void
    {
        $this->expectException(InvalidItem::class);
        $this->expectExceptionMessageIsOrContains('The item "b" is already in the batch "w".');
        EditorialCalendar::of([$this->item('a'), $this->item('b')], [new PublicationBatch('w', 'W', ['a', 'b']), new PublicationBatch('v', 'V', ['b'])]);
    }

    public function testChangesGiveANewCalendar(): void
    {
        $calendar = $this->calendar();
        $changed = $calendar->with($this->item('a')->edit('Changed'));

        self::assertSame('A', $calendar->item('a')->title);
        self::assertSame('Changed', $changed->item('a')->title);
        self::assertCount(4, $changed->items());
        self::assertCount(5, $changed->with($this->item('d'))->items());
    }

    public function testRemovingAnItemTakesItOutOfItsBatch(): void
    {
        $calendar = $this->calendar();

        $withoutB = $calendar->without('b');
        self::assertSame(['a', 'c', '42'], array_map(static fn (PlannedItem $item): string => $item->id, $withoutB->items()));
        self::assertSame(['c'], $withoutB->batch('wave')->items);
        self::assertSame('Wave', $withoutB->batch('wave')->title);
        self::assertSame('2026-12-01T12:00:00+00:00', $withoutB->batch('wave')->at?->format('c'));

        self::assertSame([], $withoutB->without('c')->batches());
        self::assertSame($calendar->batch('wave'), $calendar->without('a')->batch('wave'));

        $this->expectException(UnknownItem::class);
        $calendar->without('x');
    }

    public function testABatchIsRemoved(): void
    {
        $calendar = $this->calendar()->withoutBatch('wave');

        self::assertSame([], $calendar->batches());
        self::assertCount(4, $calendar->items());

        $this->expectException(UnknownItem::class);
        $calendar->withoutBatch('wave');
    }

    public function testTheMomentOfAnItemIsItsBatchesWhenItHasOne(): void
    {
        $calendar = $this->calendar();

        self::assertSame('2026-11-30T09:00:00+00:00', $calendar->moment($calendar->item('a'))?->format('c'));
        self::assertSame('2026-12-01T12:00:00+00:00', $calendar->moment($calendar->item('b'))?->format('c'));
        self::assertNull($calendar->moment($calendar->item('42')));

        $manual = EditorialCalendar::of([$this->item('a', new DateTimeImmutable('2026-11-30Z'))], [new PublicationBatch('m', 'M', ['a'])]);
        self::assertNull($manual->moment($manual->item('a')));
    }

    public function testAScheduledItemTakesItsPlannedMoment(): void
    {
        $calendar = $this->calendar()
            ->transition('b', ItemState::Drafting)
            ->transition('b', ItemState::Ready)
            ->transition('b', ItemState::Scheduled);

        self::assertSame(ItemState::Scheduled, $calendar->item('b')->state);
        self::assertSame('2026-12-01T12:00:00+00:00', $calendar->item('b')->publishAt?->format('c'));

        $own = $this->calendar()->transition('a', ItemState::Drafting)->transition('a', ItemState::Ready)->transition('a', ItemState::Scheduled, new DateTimeImmutable('2026-12-24Z'));
        self::assertSame('2026-12-24T00:00:00+00:00', $own->item('a')->publishAt?->format('c'));

        $drafted = $this->calendar()->transition('b', ItemState::Drafting);
        self::assertNull($drafted->item('b')->publishAt);
    }

    public function testAnItemOfABatchIsPublishedOnlyWithIt(): void
    {
        $calendar = $this->ready($this->calendar(), 'b');

        self::assertSame(ItemState::Withdrawn, $calendar->transition('b', ItemState::Withdrawn)->item('b')->state);

        $this->expectException(InvalidTransition::class);
        $this->expectExceptionMessageIsOrContains('The item "b" is published with its batch "wave", as a whole.');
        $calendar->transition('b', ItemState::Published);
    }

    public function testAnItemAloneIsPublishedByHand(): void
    {
        $calendar = $this->ready($this->calendar(), 'a')->transition('a', ItemState::Published, new DateTimeImmutable('2026-11-30 10:00Z'));

        self::assertSame(ItemState::Published, $calendar->item('a')->state);
        self::assertSame('2026-11-30T10:00:00+00:00', $calendar->item('a')->publishAt?->format('c'));
    }

    public function testABatchIsAppliedWhole(): void
    {
        $calendar = $this->ready($this->ready($this->calendar(), 'b'), 'c');
        $applied = $calendar->applyBatch('wave', new DateTimeImmutable('2026-12-01 12:00:05Z'));

        foreach (['b', 'c'] as $id) {
            self::assertSame(ItemState::Published, $applied->item($id)->state);
            self::assertSame('2026-12-01T12:00:05+00:00', $applied->item($id)->publishAt?->format('c'));
        }

        self::assertSame(ItemState::Idea, $applied->item('a')->state);
        self::assertSame(ItemState::Ready, $calendar->item('b')->state);
        self::assertSame([], $applied->dueBatches(new DateTimeImmutable('2027-01-01Z')));
    }

    public function testABatchWithAnItemThatRefusesIsNotAppliedAtAll(): void
    {
        $calendar = $this->ready($this->calendar(), 'b');

        try {
            $calendar->applyBatch('wave', new DateTimeImmutable('2026-12-01Z'));
            self::fail('A batch was applied in part.');
        } catch (BatchFailed $batchFailed) {
            self::assertSame('wave', $batchFailed->batch);
            self::assertSame('c', $batchFailed->item);
            self::assertSame('The batch "wave" was not applied: The item "c" cannot go from "idea" to "published".', $batchFailed->getMessage());
            self::assertInstanceOf(InvalidTransition::class, $batchFailed->getPrevious());
            self::assertSame(0, $batchFailed->getCode());
        }

        self::assertSame(ItemState::Ready, $calendar->item('b')->state);
    }

    public function testApplyingAnUnknownBatchThrows(): void
    {
        $this->expectException(UnknownItem::class);
        $this->calendar()->applyBatch('x', new DateTimeImmutable());
    }

    public function testDueBatchesAreThoseWhoseMomentHasComeInTheirOrder(): void
    {
        $calendar = EditorialCalendar::of(
            [$this->item('a'), $this->item('b'), $this->item('c'), $this->item('d'), $this->item('e')],
            [
                new PublicationBatch('late', 'L', ['a'], new DateTimeImmutable('2026-12-02Z')),
                new PublicationBatch('early', 'E', ['b'], new DateTimeImmutable('2026-12-01Z')),
                new PublicationBatch('same', 'S', ['e'], new DateTimeImmutable('2026-12-01Z')),
                new PublicationBatch('future', 'F', ['c'], new DateTimeImmutable('2026-12-03Z')),
                new PublicationBatch('manual', 'M', ['d']),
            ],
        );

        $ids = static fn (array $batches): array => array_map(static fn (PublicationBatch $batch): string => $batch->id, $batches);

        self::assertSame(['early', 'same', 'late'], $ids($calendar->dueBatches(new DateTimeImmutable('2026-12-02Z'))));
        self::assertSame(['early', 'same'], $ids($calendar->dueBatches(new DateTimeImmutable('2026-12-01 23:59:59Z'))));
        self::assertSame([], $ids($calendar->dueBatches(new DateTimeImmutable('2026-11-30 23:59:59Z'))));
        self::assertSame(['manual'], $ids($calendar->manualBatches()));

        $withdrawn = $calendar->transition('b', ItemState::Withdrawn)->transition('d', ItemState::Withdrawn);
        self::assertSame(['same', 'late'], $ids($withdrawn->dueBatches(new DateTimeImmutable('2026-12-02Z'))));
        self::assertSame([], $withdrawn->manualBatches());
    }

    public function testAPartlyPendingBatchIsStillDue(): void
    {
        $calendar = EditorialCalendar::of([$this->item('a'), $this->item('b')], [new PublicationBatch('w', 'W', ['a', 'b'], new DateTimeImmutable('2026-12-01Z'))])
            ->transition('a', ItemState::Withdrawn);

        self::assertCount(1, $calendar->dueBatches(new DateTimeImmutable('2026-12-01Z')));
    }

    public function testUnscheduledItemsHaveNoMomentAndNoBatch(): void
    {
        $calendar = $this->calendar()->with($this->item('w')->transitionTo(ItemState::Withdrawn));

        self::assertSame(['42'], array_map(static fn (PlannedItem $item): string => $item->id, $calendar->unscheduled()));
        self::assertSame(['42'], array_map(static fn (PlannedItem $item): string => $item->id, $calendar->unscheduled(Filter::all()->byStates(ItemState::Idea))));
        self::assertSame([], $calendar->unscheduled(Filter::all()->byStates(ItemState::Drafting)));
    }

    private function calendar(): EditorialCalendar
    {
        return EditorialCalendar::of(
            [
                $this->item('a', new DateTimeImmutable('2026-11-30 10:00+01:00')),
                $this->item('b'),
                $this->item('c', new DateTimeImmutable('2026-12-05Z')),
                $this->item('42'),
            ],
            [new PublicationBatch('wave', 'Wave', ['b', 'c'], new DateTimeImmutable('2026-12-01 12:00Z'))],
        );
    }

    private function ready(EditorialCalendar $calendar, string $id): EditorialCalendar
    {
        return $calendar->transition($id, ItemState::Drafting)->transition($id, ItemState::Ready);
    }

    private function item(string $id, ?DateTimeImmutable $at = null): PlannedItem
    {
        return PlannedItem::plan($id, ResourceReference::of('article', $id), strtoupper($id), 'fr', new AuthorId('u'), $at);
    }
}
