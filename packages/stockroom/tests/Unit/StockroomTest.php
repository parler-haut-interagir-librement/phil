<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit;

use Override;
use Phil\Stockroom\Container;
use Phil\Stockroom\Contract\Balances;
use Phil\Stockroom\Contract\Containers;
use Phil\Stockroom\Exception\InsufficientStock;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Exception\UnknownStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\ItemCondition;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Place;
use Phil\Stockroom\Stockroom;
use Phil\Stockroom\Tests\Unit\Fixture\Stock;
use Phil\Stockroom\Tracking;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stockroom::class)]
final class StockroomTest extends TestCase
{
    private Stock $stock;

    private Stockroom $room;

    #[Override]
    protected function setUp(): void
    {
        $this->stock = new Stock();
        $this->room = $this->stock->stockroom;
    }

    public function testContainersNestAndGiveTheirPath(): void
    {
        $warehouse = $this->room->addContainer('Warehouse', id: 'warehouse');
        $shelf = $this->room->addContainer('Shelf B', 'warehouse', 'S-B', 'shelf');
        $crate = $this->room->addContainer('Crate 3', 'shelf', id: 'crate');

        self::assertSame(['warehouse', null], [$warehouse->id, $warehouse->parent]);
        self::assertSame(['shelf', 'warehouse', 'S-B'], [$shelf->id, $shelf->parent, $shelf->code]);
        self::assertSame(['warehouse', 'shelf', 'crate'], $this->ids($this->room->path('crate')));
        self::assertSame($crate, $this->room->container('crate'));
        self::assertSame(['warehouse'], $this->ids($this->room->path('warehouse')));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->room->addContainer('Bag')->id);
    }

    public function testAContainerIdIsUnique(): void
    {
        $this->room->addContainer('Warehouse', id: 'warehouse');

        $this->expectExceptionMessageIsOrContains('There is a container "warehouse" already.');
        $this->room->addContainer('Again', id: 'warehouse');
    }

    public function testAContainerNeedsAnExistingParent(): void
    {
        $this->expectExceptionObject(UnknownStock::container('nowhere'));
        $this->room->addContainer('Crate', 'nowhere');
    }

    public function testContainersNestToABound(): void
    {
        $parent = null;

        for ($depth = 1; $depth <= Stockroom::MAX_DEPTH; ++$depth) {
            $parent = $this->room->addContainer('Level '.$depth, $parent, id: 'level-'.$depth)->id;
        }

        self::assertCount(32, $this->room->path('level-32'));
        $this->room->addContainer('Beside', 'level-31', id: 'beside');

        try {
            $this->room->addContainer('Too deep', 'level-32');
            self::fail('A container sat 33 deep.');
        } catch (InvalidStock $invalidStock) {
            self::assertSame('A container sits at most 32 deep.', $invalidStock->getMessage());
        }

        $this->room->addContainer('Top', id: 'top');

        $this->expectExceptionMessageIsOrContains('A container sits at most 32 deep.');
        $this->room->moveContainer('top', 'level-32');
    }

    public function testAMovedContainerTakesWhatItHolds(): void
    {
        $this->room->addContainer('Warehouse', id: 'warehouse');
        $this->room->addContainer('Truck', id: 'truck');
        $this->room->addContainer('Crate', 'warehouse', id: 'crate');

        self::assertSame('truck', $this->room->moveContainer('crate', 'truck')->parent);
        self::assertSame(['truck', 'crate'], $this->ids($this->room->path('crate')));
        self::assertNull($this->room->moveContainer('crate', null)->parent);
        self::assertNull($this->room->container('crate')->parent);
    }

    public function testAContainerDoesNotMoveIntoWhatItHolds(): void
    {
        $this->room->addContainer('Warehouse', id: 'warehouse');
        $this->room->addContainer('Shelf', 'warehouse', id: 'shelf');
        $this->room->addContainer('Crate', 'shelf', id: 'crate');

        $this->expectExceptionMessageIsOrContains('A container does not sit in itself, nor in a container it holds.');
        $this->room->moveContainer('warehouse', 'crate');
    }

    public function testALoopOfParentsIsCaught(): void
    {
        $this->stock->containers->save(new Container('a', 'A', 'b'));
        $this->stock->containers->save(new Container('b', 'B', 'a'));

        $this->expectExceptionMessageIsOrContains('The container "a" sits deeper than 32 containers.');
        $this->room->path('a');
    }

    public function testAnUnknownContainer(): void
    {
        $this->expectExceptionObject(UnknownStock::container('x'));
        $this->room->moveContainer('x', null);
    }

    public function testItemsHaveUniqueCodesAndExistingHomes(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $sword = $this->room->addItem('FS', 'Foam sword', Tracking::Quantity, 'crate', 'sword');

        self::assertSame(['sword', 'FS', 'crate'], [$sword->id, $sword->code, $sword->home]);
        self::assertSame($sword, $this->room->item('sword'));
        self::assertSame($sword, $this->room->itemByCode('FS'));
        self::assertNull($this->room->item('nothing'));
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->room->addItem('LA', 'Lantern')->id);

        foreach ([['FS', 'other'], ['XX', 'sword']] as [$code, $id]) {
            try {
                $this->room->addItem($code, 'Again', id: $id);
                self::fail('An item was added twice.');
            } catch (InvalidStock $invalid) {
                self::assertSame(\sprintf('There is an item "%s" or with the code "%s" already.', $id, $code), $invalid->getMessage());
            }
        }

        try {
            $this->room->itemByCode('none');
            self::fail('An unknown code was found.');
        } catch (UnknownStock $unknownStock) {
            self::assertSame('There is no item "none".', $unknownStock->getMessage());
        }

        $this->expectExceptionObject(UnknownStock::container('attic'));
        $this->room->addItem('BA', 'Banner', home: 'attic');
    }

    public function testConditionAndHomeChange(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');

        self::assertSame(ItemCondition::NeedsRepair, $this->room->setCondition('sword', ItemCondition::NeedsRepair)->condition);
        self::assertSame(ItemCondition::NeedsRepair, $this->room->item('sword')?->condition);
        self::assertSame('crate', $this->room->setHome('sword', 'crate')->home);
        self::assertSame('crate', $this->room->item('sword')?->home);
        self::assertNull($this->room->setHome('sword', null)->home);

        try {
            $this->room->setHome('sword', 'attic');
            self::fail('An item was homed in a container that does not exist.');
        } catch (UnknownStock) {
            self::assertNull($this->room->item('sword')?->home);
        }

        $this->expectExceptionObject(UnknownStock::item('ghost'));
        $this->room->setCondition('ghost', ItemCondition::Usable);
    }

    public function testEveryMovementIsJournaledAndBalanced(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('FS', 'Foam sword', home: 'crate', id: 'sword');

        $in = $this->room->receive('sword', 'crate', 10, 'order-1', 'Bought');
        self::assertSame([MovementKind::In, 10, null, 'container/crate', 'order-1', 'Bought', '2026-11-13T14:00:00+00:00'], [$in->kind, $in->quantity, $in->from, $in->to?->key(), $in->reference, $in->note, $in->at->format(\DATE_ATOM)]);

        $transfer = $this->room->transfer('sword', 'crate', 'shelf', 3, 'tidy', 'Moved');
        self::assertSame([MovementKind::Transfer, 3, 'container/crate', 'container/shelf', 'tidy', 'Moved'], [$transfer->kind, $transfer->quantity, $transfer->from?->key(), $transfer->to?->key(), $transfer->reference, $transfer->note]);

        $out = $this->room->issue('sword', Place::event('fair'), 8, reference: 'fair', note: 'Loaded');
        self::assertSame([['container/crate', 7], ['container/shelf', 1]], array_map(static fn (Movement $movement): array => [$movement->from?->key(), $movement->quantity], $out), 'the home first');
        self::assertSame(['event/fair', 'fair', 'Loaded', MovementKind::Out], [$out[1]->to?->key(), $out[1]->reference, $out[1]->note, $out[1]->kind]);

        $loss = $this->room->lose('sword', Place::event('fair'), 2, 'fair', 'Broken');
        self::assertSame([MovementKind::Loss, 2, 'event/fair', null, 'fair', 'Broken'], [$loss->kind, $loss->quantity, $loss->from?->key(), $loss->to, $loss->reference, $loss->note]);

        $back = $this->room->giveBack('sword', Place::event('fair'), 5, reference: 'fair', note: 'Back');
        self::assertSame([MovementKind::Return, 5, 'event/fair', 'container/crate', 'fair', 'Back'], [$back->kind, $back->quantity, $back->from?->key(), $back->to?->key(), $back->reference, $back->note]);

        $elsewhere = $this->room->giveBack('sword', Place::event('fair'), 1, 'shelf');
        self::assertSame('container/shelf', $elsewhere->to?->key());

        self::assertSame([['container/crate', 5], ['container/shelf', 3]], $this->holdings('sword'));
        self::assertSame(8, $this->room->available('sword'));
        self::assertSame([$elsewhere, $back, $loss, $out[1], $out[0], $transfer, $in], $this->room->history('sword'));
        self::assertSame([$elsewhere, $back], $this->room->history('sword', 2));
        self::assertSame([$back, $loss, $out[1], $out[0]], $this->room->movementsOf('fair'));
        self::assertSame([$back], $this->room->movementsOf('fair', 1));
    }

    public function testIssuingFromANamedContainer(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('FS', 'Foam sword', home: 'crate', id: 'sword');
        $this->room->receive('sword', 'crate', 5);
        $this->room->receive('sword', 'shelf', 2);

        $out = $this->room->issue('sword', Place::holder('member-7'), 2, 'shelf');
        self::assertCount(1, $out);
        self::assertSame(['container/shelf', 'holder/member-7', 2], [$out[0]->from?->key(), $out[0]->to?->key(), $out[0]->quantity]);
        self::assertSame([['container/crate', 5], ['holder/member-7', 2]], $this->holdings('sword'));

        try {
            $this->room->issue('sword', Place::holder('member-7'), 1, 'shelf');
            self::fail('An empty container gave a piece.');
        } catch (InsufficientStock $insufficientStock) {
            self::assertSame('container/shelf holds 0 of "FS", not 1.', $insufficientStock->getMessage());
        }

        $this->expectExceptionObject(UnknownStock::container('attic'));
        $this->room->issue('sword', Place::holder('member-7'), 1, 'attic');
    }

    public function testIssuingTakesNoMoreThanThereIs(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'shelf', 2);
        $this->room->receive('sword', 'crate', 3);

        try {
            $this->room->issue('sword', Place::event('fair'), 6);
            self::fail('Six pieces left out of five.');
        } catch (InsufficientStock $insufficientStock) {
            self::assertSame('The stockroom holds 5 of "FS", not 6.', $insufficientStock->getMessage());
        }

        self::assertSame([['container/crate', 3], ['container/shelf', 2]], $this->holdings('sword'), 'nothing moved');
        $out = $this->room->issue('sword', Place::event('fair'), 5);
        self::assertSame([['container/crate', 3], ['container/shelf', 2]], array_map(static fn (Movement $movement): array => [$movement->from?->key(), $movement->quantity], $out), 'without a home, in the order of the keys');
    }

    public function testIssuingGoesOutsideOnly(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');

        $this->expectExceptionMessageIsOrContains('Stock goes out to an event or a holder; between containers, it is transferred.');
        $this->room->issue('sword', Place::container('crate'));
    }

    public function testAnItemUnderRepairDoesNotLeave(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'crate', 4);
        $this->room->setCondition('sword', ItemCondition::NeedsRepair);

        self::assertSame(0, $this->room->available('sword'));
        self::assertSame(0, $this->room->available('ghost'));
        $this->room->addContainer('Bench', id: 'bench');
        self::assertSame(MovementKind::Transfer, $this->room->transfer('sword', 'crate', 'bench')->kind, 'it still moves inside');

        $this->expectExceptionMessageIsOrContains('The item "FS" does not leave: it is needs_repair.');
        $this->room->issue('sword', Place::event('fair'));
    }

    public function testARetiredItemIsRefusedWithItsCondition(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'crate', 4);
        $this->room->setCondition('sword', ItemCondition::Retired);

        $this->expectExceptionMessageIsOrContains('The item "FS" does not leave: it is retired.');
        $this->room->issue('sword', Place::event('fair'));
    }

    public function testATrackedUnitIsInOnePlaceAtATime(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('PJ-1', 'Projector', Tracking::Unit, 'crate', 'projector');
        $this->room->receive('projector', 'crate');

        foreach ([fn (): mixed => $this->room->receive('projector', 'crate'), fn (): mixed => $this->room->receive('projector', 'crate', 2), fn (): mixed => $this->room->issue('projector', Place::event('fair'), 2)] as $refused) {
            try {
                $refused();
                self::fail('A unit moved twice.');
            } catch (InsufficientStock $short) {
                self::assertSame('The unit "PJ-1" is in stock already.', $short->getMessage());
            } catch (InvalidStock $invalid) {
                self::assertSame('The unit "PJ-1" moves one at a time, not 2.', $invalid->getMessage());
            }
        }

        $this->room->issue('projector', Place::event('fair'));
        $this->room->lose('projector', Place::event('fair'));
        self::assertSame([], $this->room->holdings('projector'));
        self::assertSame(MovementKind::In, $this->room->receive('projector', 'crate', note: 'Found')->kind, 'a lost unit found comes back in');
        self::assertSame(1, $this->room->available('projector'));
    }

    public function testATransferNeedsBothContainersAndThePieces(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'crate', 2);

        foreach (['crate' => 'attic', 'attic' => 'crate'] as $from => $to) {
            try {
                $this->room->transfer('sword', $from, $to);
                self::fail('A transfer went through a container that does not exist.');
            } catch (UnknownStock $unknown) {
                self::assertSame('There is no container "attic".', $unknown->getMessage());
            }
        }

        try {
            $this->room->transfer('sword', 'crate', 'shelf', 3);
            self::fail('Three pieces moved out of two.');
        } catch (InsufficientStock) {
        }

        $this->expectException(InvalidStock::class);
        $this->room->transfer('sword', 'crate', 'crate');
    }

    public function testALossNeedsThePieces(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'crate', 2);

        self::assertSame('container/crate', $this->room->lose('sword', Place::container('crate'))->from?->key());

        $this->expectExceptionMessageIsOrContains('container/crate holds 1 of "FS", not 2.');
        $this->room->lose('sword', Place::container('crate'), 2);
    }

    public function testAQuantityIsCheckedBeforeTheStock(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');

        $this->expectExceptionMessageIsOrContains('A quantity is from 1 to 1000000, not 0.');
        $this->room->lose('sword', Place::container('crate'), 0);
    }

    public function testAReturnComesFromOutsideToAContainer(): void
    {
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->receive('sword', 'crate', 2);
        $this->room->issue('sword', Place::holder('jo'), 2);

        foreach ([[Place::holder('jo'), null, 'The item "FS" has no home: name the container it goes back to.'], [Place::container('crate'), 'crate', 'Stock comes back from an event or a holder; between containers, it is transferred.'], [Place::holder('jo'), 'attic', 'There is no container "attic".'], [Place::holder('al'), 'crate', 'holder/al holds 0 of "FS", not 1.']] as [$from, $to, $message]) {
            try {
                $this->room->giveBack('sword', $from, 1, $to);
                self::fail('A return was refused nothing.');
            } catch (InvalidStock|UnknownStock|InsufficientStock $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertSame('container/crate', $this->room->giveBack('sword', Place::holder('jo'), 2, 'crate')->to?->key());
    }

    public function testUnknownItemsAreRefused(): void
    {
        $this->room->addContainer('Crate', id: 'crate');

        foreach ([fn (): mixed => $this->room->receive('ghost', 'crate'), fn (): mixed => $this->room->issue('ghost', Place::event('e')), fn (): mixed => $this->room->transfer('ghost', 'crate', 'crate'), fn (): mixed => $this->room->lose('ghost', Place::event('e')), fn (): mixed => $this->room->giveBack('ghost', Place::event('e'), to: 'crate'), fn (): mixed => $this->room->setHome('ghost', null)] as $call) {
            try {
                $call();
                self::fail('An unknown item moved.');
            } catch (UnknownStock $unknown) {
                self::assertSame('There is no item "ghost".', $unknown->getMessage());
            }
        }

        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->expectExceptionObject(UnknownStock::container('attic'));
        $this->room->receive('sword', 'attic');
    }

    public function testTheContentsOfAContainerAndThoseNestedInIt(): void
    {
        $this->room->addContainer('Warehouse', id: 'warehouse');
        $this->room->addContainer('Shelf', 'warehouse', id: 'shelf');
        $this->room->addContainer('Crate', 'shelf', id: 'crate');
        $this->room->addContainer('Bag', 'warehouse', id: 'bag');
        $this->room->addItem('FS', 'Foam sword', id: 'sword');
        $this->room->addItem('LA', 'Lantern', id: 'lantern');
        $this->room->receive('sword', 'warehouse', 1);
        $this->room->receive('lantern', 'crate', 2);
        $this->room->receive('sword', 'bag', 3);
        $this->room->receive('lantern', 'warehouse', 4);

        self::assertSame([['lantern', 'container/warehouse', 4], ['sword', 'container/warehouse', 1]], $this->lines($this->room->contents('warehouse')));
        self::assertSame([['lantern', 'container/warehouse', 4], ['sword', 'container/warehouse', 1], ['sword', 'container/bag', 3], ['lantern', 'container/crate', 2]], $this->lines($this->room->contents('warehouse', true)));
        self::assertSame([['lantern', 'container/warehouse', 4], ['sword', 'container/warehouse', 1], ['sword', 'container/bag', 3]], $this->lines($this->room->contents('warehouse', true, 3)));
        self::assertSame([['lantern', 'container/warehouse', 4]], $this->lines($this->room->contents('warehouse', true, 1)));
        self::assertSame([], $this->room->contents('shelf'));
        self::assertSame([['lantern', 'container/crate', 2]], $this->lines($this->room->contents('shelf', true, 1000)));

        foreach ([0, 1001] as $limit) {
            try {
                $this->room->contents('warehouse', limit: $limit);
                self::fail('A listing out of bounds was given.');
            } catch (InvalidStock $invalid) {
                self::assertSame(\sprintf('A listing gives 1 to 1000 lines, not %d.', $limit), $invalid->getMessage());
            }
        }

        foreach ([0, 1001] as $limit) {
            try {
                $this->room->history('sword', $limit);
                self::fail('A history out of bounds was given.');
            } catch (InvalidStock) {
            }

            try {
                $this->room->movementsOf('x', $limit);
                self::fail('A history out of bounds was given.');
            } catch (InvalidStock) {
            }
        }

        $this->expectExceptionObject(UnknownStock::container('attic'));
        $this->room->contents('attic');
    }

    public function testTheSourcesOfAnItemPutItsHomeFirst(): void
    {
        $this->room->addContainer('A', id: 'a');
        $this->room->addContainer('B', id: 'b');
        $this->room->addContainer('C', id: 'c');

        $sword = $this->room->addItem('FS', 'Foam sword', home: 'b', id: 'sword');
        $this->room->receive('sword', 'c', 1);
        $this->room->receive('sword', 'a', 2);
        $this->room->receive('sword', 'b', 3);
        $this->room->issue('sword', Place::event('fair'), 1, 'a');

        self::assertSame([['sword', 'container/b', 3], ['sword', 'container/a', 1], ['sword', 'container/c', 1]], $this->lines($this->room->sources($sword)));
        self::assertSame(5, $this->room->available('sword'), 'what is at the event does not count');
    }

    public function testAPathStopsAtTheBoundWherever32ContainersAreStacked(): void
    {
        for ($depth = 1; $depth <= 33; ++$depth) {
            $this->stock->containers->save(new Container('level-'.$depth, 'Level '.$depth, 1 === $depth ? null : 'level-'.($depth - 1)));
        }

        self::assertCount(32, $this->room->path('level-32'));

        $this->expectExceptionMessageIsOrContains('The container "level-33" sits deeper than 32 containers.');
        $this->room->path('level-33');
    }

    public function testTheListingsGiveAHundredLinesUnlessAskedForMore(): void
    {
        $this->room->addContainer('Bin', id: 'bin');
        $this->room->addItem('PC', 'Piece', id: 'piece');

        for ($n = 1; $n <= 101; ++$n) {
            $this->room->addItem('P'.$n, 'Piece '.$n, id: 'piece-'.$n);
            $this->room->receive('piece-'.$n, 'bin', 1, 'batch');
            $this->room->receive('piece', 'bin', 1, 'batch');
        }

        self::assertCount(100, $this->room->contents('bin'));
        self::assertCount(100, $this->room->contents('bin', true));
        self::assertCount(101, $this->room->contents('bin', limit: 101));
        self::assertCount(100, $this->room->history('piece'));
        self::assertCount(100, $this->room->movementsOf('batch'));
        self::assertCount(101, $this->room->history('piece', 101));
        self::assertCount(1, $this->room->history('piece', 1));
    }

    public function testANestedListingAsksEachContainerForWhatIsLeftOfTheLimit(): void
    {
        $spy = new class(new InMemoryBalances()) implements Balances {
            /** @var list<int> */
            public array $limits = [];

            public function __construct(private readonly InMemoryBalances $balances)
            {
            }

            public function quantity(string $item, Place $place): int
            {
                return $this->balances->quantity($item, $place);
            }

            public function set(string $item, Place $place, int $quantity): void
            {
                $this->balances->set($item, $place, $quantity);
            }

            public function of(string $item): array
            {
                return $this->balances->of($item);
            }

            public function at(Place $place, int $limit): array
            {
                $this->limits[] = $limit;

                return $this->balances->at($place, $limit);
            }
        };
        $room = new Stockroom($this->stock->containers, $this->stock->items, $spy, $this->stock->journal, $this->stock->clock);
        $room->addContainer('Warehouse', id: 'warehouse');
        $room->addContainer('Bag', 'warehouse', id: 'bag');
        $room->addContainer('Shelf', 'warehouse', id: 'shelf');
        $room->addItem('A', 'A', id: 'a');
        $room->addItem('B', 'B', id: 'b');
        $room->addItem('C', 'C', id: 'c');
        $room->receive('a', 'warehouse');
        $room->receive('b', 'warehouse');
        $room->receive('c', 'bag');

        self::assertCount(3, $room->contents('warehouse', true, 3));
        self::assertSame([3, 1], $spy->limits, 'the limit is reached: the shelf is not asked for nothing');
    }

    public function testANestedListingWalksAtMostTenThousandContainers(): void
    {
        $containers = new class implements Containers {
            public function get(string $id): ?Container
            {
                return str_starts_with($id, 'c-') ? new Container($id, 'Level') : null;
            }

            public function save(Container $container): void
            {
            }

            public function children(string $id, int $limit): array
            {
                $next = (int) substr($id, 2) + 1;

                return $next <= 10_005 ? [new Container('c-'.$next, 'Level', $id)] : [];
            }
        };
        $balances = new class implements Balances {
            public function quantity(string $item, Place $place): int
            {
                return 0;
            }

            public function set(string $item, Place $place, int $quantity): void
            {
            }

            public function of(string $item): array
            {
                return [];
            }

            public function at(Place $place, int $limit): array
            {
                return \in_array($place->reference, ['c-9999', 'c-10000'], true) ? [new Holding('piece', $place, 1)] : [];
            }
        };
        $room = new Stockroom($containers, $this->stock->items, $balances, $this->stock->journal, $this->stock->clock);

        self::assertSame(['container/c-9999'], array_map(static fn (Holding $holding): string => $holding->place->key(), $room->contents('c-0', true)));
    }

    public function testATrackedUnitIsReceivedOneAtATimeOnly(): void
    {
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('SN', 'Serial', Tracking::Unit, id: 'unit');

        $this->expectExceptionMessageIsOrContains('The unit "SN" moves one at a time, not 2.');
        $this->room->receive('unit', 'shelf', 2);
    }

    public function testTheDefaultQuantityIsOnePiece(): void
    {
        $this->room->addContainer('A', id: 'a');
        $this->room->addContainer('B', id: 'b');
        $this->room->addItem('FS', 'Foam sword', home: 'a', id: 'sword');
        $this->room->receive('sword', 'a', 5);
        $this->room->issue('sword', Place::holder('jo'), 3);

        self::assertSame(1, $this->room->transfer('sword', 'a', 'b')->quantity);
        self::assertSame(1, $this->room->giveBack('sword', Place::holder('jo'))->quantity);
        self::assertSame(1, $this->room->lose('sword', Place::holder('jo'))->quantity);
    }

    public function testIssuingStopsTakingOnceTheQuantityIsFound(): void
    {
        $this->room->addContainer('A', id: 'a');
        $this->room->addContainer('B', id: 'b');
        $this->room->addItem('FS', 'Foam sword', home: 'a', id: 'sword');
        $this->room->receive('sword', 'a', 2);
        $this->room->receive('sword', 'b', 3);

        $movements = $this->room->issue('sword', Place::event('fair'), 2);

        self::assertSame([['container/a', 2]], array_map(static fn (Movement $movement): array => [$movement->from?->key(), $movement->quantity], $movements));
    }

    /**
     * @param list<Container> $containers
     *
     * @return list<string>
     */
    private function ids(array $containers): array
    {
        return array_map(static fn (Container $container): string => $container->id, $containers);
    }

    /**
     * @return list<array{string, int}>
     */
    private function holdings(string $item): array
    {
        return array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $this->room->holdings($item));
    }

    /**
     * @param list<Holding> $holdings
     *
     * @return list<array{string, string, int}>
     */
    private function lines(array $holdings): array
    {
        return array_map(static fn (Holding $holding): array => [$holding->item, $holding->place->key(), $holding->quantity], $holdings);
    }
}
