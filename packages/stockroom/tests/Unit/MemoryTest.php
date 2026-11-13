<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit;

use DateTimeImmutable;
use Phil\Stockroom\Container;
use Phil\Stockroom\Holding;
use Phil\Stockroom\Item;
use Phil\Stockroom\LoadList;
use Phil\Stockroom\Memory\InMemoryBalances;
use Phil\Stockroom\Memory\InMemoryContainers;
use Phil\Stockroom\Memory\InMemoryItems;
use Phil\Stockroom\Memory\InMemoryJournal;
use Phil\Stockroom\Memory\InMemoryLoadLists;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryContainers::class)]
#[CoversClass(InMemoryItems::class)]
#[CoversClass(InMemoryBalances::class)]
#[CoversClass(InMemoryJournal::class)]
#[CoversClass(InMemoryLoadLists::class)]
final class MemoryTest extends TestCase
{
    public function testContainers(): void
    {
        $containers = new InMemoryContainers();
        $containers->save(new Container('top', 'Top'));
        $containers->save(new Container('b', 'B', 'top'));
        $containers->save(new Container('a', 'A', 'top'));
        $containers->save(new Container('c', 'C', 'b'));
        $containers->save(new Container('10', 'Ten', 'top'));
        $containers->save(new Container('9', 'Nine', 'top'));

        self::assertSame('Top', $containers->get('top')?->name);
        self::assertSame(['10', '9', 'a', 'b'], array_map(static fn (Container $container): string => $container->id, $containers->children('top', 10)));
        self::assertSame(['10', '9'], array_map(static fn (Container $container): string => $container->id, $containers->children('top', 2)));
        self::assertSame([], $containers->children('top', -1));
        self::assertSame([], $containers->children('a', 10));

        $containers->reset();
        self::assertNull($containers->get('top'));
    }

    public function testItems(): void
    {
        $items = new InMemoryItems();
        $items->save(new Item('a', 'FS', 'Foam sword'));
        $items->save(new Item('b', 'LA', 'Lantern'));

        self::assertSame('Lantern', $items->get('b')?->name);
        self::assertSame('b', $items->byCode('LA')?->id);
        self::assertNull($items->byCode('XX'));

        $items->reset();
        self::assertNull($items->get('a'));
        self::assertNull($items->byCode('FS'));
    }

    public function testBalances(): void
    {
        $balances = new InMemoryBalances();
        $balances->set('sword', Place::event('fair'), 2);
        $balances->set('sword', Place::container('crate'), 5);
        $balances->set('10', Place::container('crate'), 1);
        $balances->set('9', Place::container('crate'), 3);
        $balances->set('lantern', Place::container('shelf'), 1);

        self::assertSame(5, $balances->quantity('sword', Place::container('crate')));
        self::assertSame(0, $balances->quantity('sword', Place::container('shelf')));
        self::assertSame([['container/crate', 5], ['event/fair', 2]], array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $balances->of('sword')));
        self::assertSame([['10', 1], ['9', 3], ['sword', 5]], array_map(static fn (Holding $holding): array => [$holding->item, $holding->quantity], $balances->at(Place::container('crate'), 10)));
        self::assertSame([['10', 1], ['9', 3]], array_map(static fn (Holding $holding): array => [$holding->item, $holding->quantity], $balances->at(Place::container('crate'), 2)));
        self::assertSame([], $balances->of('ghost'));

        $balances->set('sword', Place::container('crate'), 0);
        self::assertSame(['event/fair'], array_map(static fn (Holding $holding): string => $holding->place->key(), $balances->of('sword')));
        $balances->set('sword', Place::event('fair'), -1);
        self::assertSame([], $balances->of('sword'));

        $balances->reset();
        self::assertSame(0, $balances->quantity('lantern', Place::container('shelf')));
    }

    public function testJournal(): void
    {
        $journal = new InMemoryJournal();
        $first = new Movement('1', MovementKind::In, 'sword', 1, null, Place::container('crate'), new DateTimeImmutable(), 'fair');
        $second = new Movement('2', MovementKind::In, 'lantern', 1, null, Place::container('crate'), new DateTimeImmutable(), 'fair');
        $third = new Movement('3', MovementKind::In, 'sword', 1, null, Place::container('crate'), new DateTimeImmutable());
        $journal->append($first);
        $journal->append($second);
        $journal->append($third);

        self::assertSame([$third, $first], $journal->ofItem('sword', 10));
        self::assertSame([$third], $journal->ofItem('sword', 1));
        self::assertSame([], $journal->ofItem('sword', -1));
        self::assertSame([$second, $first], $journal->ofReference('fair', 10));

        $journal->reset();
        self::assertSame([], $journal->ofItem('sword', 10));
    }

    public function testLoadLists(): void
    {
        $lists = new InMemoryLoadLists();
        $list = new LoadList('fair', []);
        $lists->save($list);

        self::assertSame($list, $lists->get('fair'));
        self::assertNull($lists->get('gala'));

        $lists->reset();
        self::assertNull($lists->get('fair'));
    }
}
