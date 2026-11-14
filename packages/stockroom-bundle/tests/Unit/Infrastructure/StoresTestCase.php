<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Phil\Stockroom\Container;
use Phil\Stockroom\Deployments;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Item;
use Phil\Stockroom\ItemCondition;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\LoadList;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Place;
use Phil\Stockroom\Stockroom;
use Phil\Stockroom\Tracking;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The contract of the five stores, held by the adapters in memory of `phil/stockroom` and by the bundle's on each
 * database: what is saved is read back, in the order the ports name, within their limits.
 */
abstract class StoresTestCase extends TestCase
{
    abstract protected function stores(): Stores;

    public function testAContainerIsSavedReplacedAndListedUnderItsParent(): void
    {
        $containers = $this->stores()->containers;
        $containers->save(new Container('room', 'The room'));
        $containers->save(new Container('shelf-b', 'Shelf B', 'room', 'SH-B'));
        $containers->save(new Container('shelf-a', 'Shelf A', 'room'));
        $containers->save(new Container('Shelf-C', 'Shelf C', 'room'));
        $containers->save(new Container('crate', 'Crate', 'shelf-a'));

        self::assertEquals(new Container('shelf-b', 'Shelf B', 'room', 'SH-B'), $containers->get('shelf-b'));
        self::assertEquals(new Container('room', 'The room'), $containers->get('room'));
        self::assertNull($containers->get('nowhere'));
        self::assertNull($containers->get('Room'));
        self::assertSame(['Shelf-C', 'shelf-a', 'shelf-b'], $this->ids($containers->children('room', 10)));
        self::assertSame(['Shelf-C', 'shelf-a'], $this->ids($containers->children('room', 2)));
        self::assertSame(['Shelf-C'], $this->ids($containers->children('room', 1)));
        self::assertSame([], $containers->children('room', 0));
        self::assertSame([], $containers->children('room', -1));
        self::assertSame([], $containers->children('crate', 10));

        $containers->save(new Container('shelf-b', 'Shelf B, top', 'shelf-a'));
        self::assertEquals(new Container('shelf-b', 'Shelf B, top', 'shelf-a'), $containers->get('shelf-b'));
        self::assertSame(['crate', 'shelf-b'], $this->ids($containers->children('shelf-a', 10)));
        self::assertSame(['Shelf-C', 'shelf-a'], $this->ids($containers->children('room', 10)));
    }

    public function testAnItemIsSavedReplacedAndFoundByItsCode(): void
    {
        $items = $this->stores()->items;
        $sword = new Item('sword', 'SW-1', 'Foam sword', home: 'shelf-a');
        $items->save($sword);
        $items->save(new Item('cloak', 'CL-1', 'Cloak', Tracking::Unit, null, ItemCondition::NeedsRepair));

        self::assertEquals($sword, $items->get('sword'));
        self::assertEquals($sword, $items->byCode('SW-1'));
        self::assertEquals(new Item('cloak', 'CL-1', 'Cloak', Tracking::Unit, null, ItemCondition::NeedsRepair), $items->byCode('CL-1'));
        self::assertNull($items->get('Sword'));
        self::assertNull($items->byCode('sw-1'));
        self::assertNull($items->byCode('none'));

        $renamed = new Item('sword', 'SW-2', 'Foam sword, long', Tracking::Unit, null, ItemCondition::Retired);
        $items->save($renamed);
        self::assertEquals($renamed, $items->get('sword'));
        self::assertEquals($renamed, $items->byCode('SW-2'));
        self::assertNull($items->byCode('SW-1'));
    }

    public function testABalanceIsSetReadAndRemovedAtZero(): void
    {
        $balances = $this->stores()->balances;
        $shelf = Place::container('shelf');
        $event = Place::event('larp-7');
        $balances->set('sword', $shelf, 40);
        $balances->set('sword', $event, 12);
        $balances->set('sword', Place::holder('loan-1'), 1);
        $balances->set('cloak', $shelf, 3);
        $balances->set('Axe', $shelf, 2);
        $balances->set('candle', $shelf, 0);

        self::assertSame(40, $balances->quantity('sword', $shelf));
        self::assertSame(0, $balances->quantity('sword', Place::container('other')));
        self::assertSame(0, $balances->quantity('candle', $shelf));
        self::assertSame([['container/shelf', 40], ['event/larp-7', 12], ['holder/loan-1', 1]], $this->holdings($balances->of('sword')));
        self::assertSame([], $balances->of('none'));
        self::assertSame([['Axe', 2], ['cloak', 3], ['sword', 40]], $this->items($balances->at($shelf, 10)));
        self::assertSame([['Axe', 2], ['cloak', 3]], $this->items($balances->at($shelf, 2)));
        self::assertSame([], $balances->at($shelf, 0));
        self::assertSame([], $balances->at($shelf, -5));
        self::assertTrue($balances->at($shelf, 1)[0]->place->equals($shelf));

        $balances->set('sword', $shelf, 39);
        $balances->set('sword', $shelf, 39);
        self::assertSame(39, $balances->quantity('sword', $shelf));
        $balances->set('sword', $event, 0);
        $balances->set('sword', Place::holder('loan-1'), -1);
        self::assertSame([['container/shelf', 39]], $this->holdings($balances->of('sword')));
        $balances->set('sword', $event, 0);
        self::assertSame(0, $balances->quantity('sword', $event));
    }

    public function testTheJournalIsReadTheLatestFirst(): void
    {
        $journal = $this->stores()->journal;
        $at = new DateTimeImmutable('2026-11-13 10:00:00.123456', new DateTimeZone('America/Toronto'));
        $in = new Movement('m1', MovementKind::In, 'sword', 40, null, Place::container('shelf'), $at, 'order-1', 'Bought.');
        $out = new Movement('m2', MovementKind::Out, 'sword', 12, Place::container('shelf'), Place::event('larp-7'), $at->modify('+1 hour'), 'larp-7');
        $loss = new Movement('m3', MovementKind::Loss, 'sword', 1, Place::event('larp-7'), null, $at->modify('-1 day'), 'larp-7', 'Broken.');
        $other = new Movement('m4', MovementKind::In, 'cloak', 1, null, Place::container('shelf'), $at);

        foreach ([$in, $out, $loss, $other] as $movement) {
            $journal->append($movement);
        }

        self::assertEquals([$loss, $out, $in], $journal->ofItem('sword', 10));
        self::assertEquals([$loss, $out], $journal->ofItem('sword', 2));
        self::assertSame([], $journal->ofItem('sword', 0));
        self::assertSame([], $journal->ofItem('sword', -1));
        self::assertEquals([$other], $journal->ofItem('cloak', 10));
        self::assertEquals([$loss, $out], $journal->ofReference('larp-7', 10));
        self::assertEquals([$loss], $journal->ofReference('larp-7', 1));
        self::assertSame([], $journal->ofReference('larp-7', 0));
        self::assertSame([], $journal->ofReference('Larp-7', 10));
        self::assertSame('2026-11-13T15:00:00.123456+00:00', $journal->ofItem('sword', 10)[2]->at->format('Y-m-d\TH:i:s.uP'));
    }

    public function testALoadListIsSavedReplacedAndReadInItsOrder(): void
    {
        $lists = $this->stores()->loadLists;
        $lists->save(new LoadList('larp-7', [new LoadLine('sword', 12, 'shelf'), new LoadLine('cloak'), new LoadLine('axe', 2)]));
        $lists->save(new LoadList('empty', []));

        self::assertEquals(new LoadList('larp-7', [new LoadLine('sword', 12, 'shelf'), new LoadLine('cloak'), new LoadLine('axe', 2)]), $lists->get('larp-7'));
        self::assertEquals(new LoadList('empty', []), $lists->get('empty'));
        self::assertNull($lists->get('none'));
        self::assertNull($lists->get('Larp-7'));

        $lists->save(new LoadList('larp-7', [new LoadLine('axe', 3)]));
        self::assertEquals(new LoadList('larp-7', [new LoadLine('axe', 3)]), $lists->get('larp-7'));
        self::assertEquals(new LoadList('empty', []), $lists->get('empty'));
    }

    public function testTheStockroomAndItsDeploymentsRunOnTheStores(): void
    {
        $stores = $this->stores();
        $clock = new MockClock('2026-11-13 10:00:00');
        $stockroom = new Stockroom($stores->containers, $stores->items, $stores->balances, $stores->journal, $clock);
        $deployments = new Deployments($stockroom, $stores->loadLists, $stores->balances);
        $stockroom->addContainer('Shelf', id: 'shelf');
        $stockroom->addItem('SW-1', 'Foam sword', home: 'shelf', id: 'sword');
        $stockroom->receive('sword', 'shelf', 40);

        $deployments->plan('larp-7', [new LoadLine('sword', 12)]);

        self::assertTrue($deployments->deploy('larp-7')->isComplete());
        self::assertSame(28, $stockroom->available('sword'));
        $check = $deployments->checkIn('larp-7', ['sword' => 11], writeOff: true);
        self::assertCount(2, $check->movements);
        self::assertSame([['container/shelf', 39]], $this->holdings($stockroom->holdings('sword')));
        self::assertSame([MovementKind::Loss, MovementKind::Return, MovementKind::Out, MovementKind::In], array_map(static fn (Movement $movement): MovementKind => $movement->kind, $stockroom->history('sword')));
    }

    public function testACodeIsUniqueInTheDatabase(): void
    {
        $stores = $this->stores();

        if (!$stores->connection instanceof Connection) {
            self::markTestSkipped('The adapters in memory keep the last item of a code.');
        }

        $stores->items->save(new Item('sword', 'SW-1', 'Foam sword'));

        $this->expectException(UniqueConstraintViolationException::class);
        $stores->items->save(new Item('axe', 'SW-1', 'Axe'));
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
     * @param list<Holding> $holdings
     *
     * @return list<array{string, int}>
     */
    private function holdings(array $holdings): array
    {
        return array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $holdings);
    }

    /**
     * @param list<Holding> $holdings
     *
     * @return list<array{string, int}>
     */
    private function items(array $holdings): array
    {
        return array_map(static fn (Holding $holding): array => [$holding->item, $holding->quantity], $holdings);
    }
}
