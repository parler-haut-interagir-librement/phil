<?php

declare(strict_types=1);

namespace Phil\Stockroom\Tests\Unit;

use Override;
use Phil\Stockroom\Deployments;
use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Exception\UnknownStock;
use Phil\Stockroom\Holding;
use Phil\Stockroom\ItemCondition;
use Phil\Stockroom\LoadLine;
use Phil\Stockroom\Movement;
use Phil\Stockroom\MovementKind;
use Phil\Stockroom\PickLine;
use Phil\Stockroom\Place;
use Phil\Stockroom\Shortfall;
use Phil\Stockroom\Stockroom;
use Phil\Stockroom\Tests\Unit\Fixture\Stock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Deployments::class)]
final class DeploymentsTest extends TestCase
{
    private Stock $stock;

    private Stockroom $room;

    private Deployments $deployments;

    #[Override]
    protected function setUp(): void
    {
        $this->stock = new Stock();
        $this->room = $this->stock->stockroom;
        $this->deployments = $this->stock->deployments;
        $this->room->addContainer('Crate', id: 'crate');
        $this->room->addContainer('Shelf', id: 'shelf');
        $this->room->addItem('FS', 'Foam sword', home: 'crate', id: 'sword');
        $this->room->addItem('LA', 'Lantern', home: 'shelf', id: 'lantern');
        $this->room->addItem('BA', 'Banner', id: 'banner');
        $this->room->receive('sword', 'crate', 6);
        $this->room->receive('sword', 'shelf', 4);
        $this->room->receive('lantern', 'shelf', 3);
        $this->room->receive('banner', 'crate', 1);
    }

    public function testAPlanIsCheckedAndKept(): void
    {
        $list = $this->deployments->plan('fair', [new LoadLine('sword', 8), new LoadLine('lantern', 2, 'shelf')]);

        self::assertSame($list, $this->deployments->loadList('fair'));
        self::assertSame($list, $this->stock->lists->get('fair'));

        try {
            $this->deployments->plan('fair', [new LoadLine('ghost')]);
            self::fail('A list named an item that does not exist.');
        } catch (UnknownStock $unknownStock) {
            self::assertSame('There is no item "ghost".', $unknownStock->getMessage());
        }

        try {
            $this->deployments->plan('fair', [new LoadLine('sword', 1, 'attic')]);
            self::fail('A list named a container that does not exist.');
        } catch (UnknownStock $unknownStock) {
            self::assertSame('There is no container "attic".', $unknownStock->getMessage());
        }

        self::assertSame($list, $this->deployments->loadList('fair'), 'a refused plan keeps the list');

        $this->expectExceptionObject(UnknownStock::loadList('gala'));
        $this->deployments->loadList('gala');
    }

    public function testThePickingSaysWhereEachLineIs(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 8), new LoadLine('lantern', 4, 'shelf'), new LoadLine('sword', 1, 'shelf'), new LoadLine('banner', 1, 'shelf')]);
        $this->room->setCondition('lantern', ItemCondition::NeedsRepair);

        self::assertSame([
            ['sword', 8, [['container/crate', 6], ['container/shelf', 4]], 10, false],
            ['lantern', 4, [['container/shelf', 3]], 0, true],
            ['sword', 1, [['container/shelf', 4]], 4, false],
            ['banner', 1, [], 0, true],
        ], array_map(static fn (PickLine $pick): array => [$pick->line->item, $pick->line->quantity, array_map(static fn (Holding $holding): array => [$holding->place->key(), $holding->quantity], $pick->sources), $pick->available, $pick->isShort()], $this->deployments->picking('fair')));
    }

    public function testADeploymentSendsTheListToTheEvent(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 3, 'shelf'), new LoadLine('sword', 5), new LoadLine('lantern', 2)]);

        $deployment = $this->deployments->deploy('fair', note: 'Truck 1');

        self::assertTrue($deployment->isComplete());
        self::assertSame([['sword', 'container/shelf', 3], ['sword', 'container/crate', 5], ['lantern', 'container/shelf', 2]], $this->moves($deployment->movements));
        self::assertSame([['event/fair', 'fair', 'Truck 1', MovementKind::Out]], array_values(array_unique(array_map(static fn (Movement $movement): array => [$movement->to?->key(), $movement->reference, $movement->note, $movement->kind], $deployment->movements), \SORT_REGULAR)));
        self::assertSame([['lantern', 2], ['sword', 8]], $this->outstanding('fair'));
        self::assertSame([], $this->deployments->deploy('fair')->movements, 'deploying again sends nothing more');
    }

    public function testADeploymentShortOfStockMovesNothing(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 11), new LoadLine('lantern', 2), new LoadLine('banner', 1)]);
        $this->room->setCondition('banner', ItemCondition::Retired);

        $deployment = $this->deployments->deploy('fair');

        self::assertFalse($deployment->isComplete());
        self::assertSame([], $deployment->movements);
        self::assertSame([['sword', 11, 10], ['banner', 1, 0]], $this->gaps($deployment->shortfalls));
        self::assertSame([], $this->outstanding('fair'));
    }

    public function testAPartialDeploymentIsFinishedLater(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 12)]);

        $first = $this->deployments->deploy('fair', true);
        self::assertSame([['sword', 12, 10]], $this->gaps($first->shortfalls));
        self::assertSame([['sword', 'container/crate', 6], ['sword', 'container/shelf', 4]], $this->moves($first->movements));

        $this->room->receive('sword', 'crate', 5);
        $second = $this->deployments->deploy('fair');
        self::assertTrue($second->isComplete());
        self::assertSame([['sword', 'container/crate', 2]], $this->moves($second->movements));
        self::assertSame([['sword', 12]], $this->outstanding('fair'));
    }

    public function testANamedContainerGivesItsLineOnly(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 2, 'shelf'), new LoadLine('sword', 9)]);

        $first = $this->deployments->deploy('fair', true);
        self::assertSame([['sword', 'container/shelf', 4], ['sword', 'container/crate', 6]], $this->moves($first->movements), 'what is left in the named container serves the other line last');
        self::assertSame([['sword', 11, 10]], $this->gaps($first->shortfalls));
        self::assertSame([['sword', 1, 0]], $this->gaps($this->deployments->deploy('fair')->shortfalls));
    }

    public function testANamedContainerShortOfItsLine(): void
    {
        $this->room->transfer('lantern', 'shelf', 'crate', 2);
        $this->deployments->plan('fair', [new LoadLine('lantern', 3, 'shelf')]);

        self::assertSame([['lantern', 'container/shelf', 1], ['lantern', 'container/crate', 2]], $this->moves($this->deployments->deploy('fair')->movements));
    }

    public function testTheCheckAtTheReturnFlagsWhatIsMissing(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 8), new LoadLine('lantern', 2), new LoadLine('banner', 1)]);
        $this->deployments->deploy('fair');

        $check = $this->deployments->checkIn('fair', ['sword' => 7, 'lantern' => 3, 'mug' => 1], note: 'Unloaded');

        self::assertFalse($check->isComplete());
        self::assertSame([['banner', 1, 0], ['sword', 8, 7]], $this->gaps($check->missing));
        self::assertSame(['lantern' => 1, 'mug' => 1], $check->surplus);
        self::assertSame([['lantern', 'container/shelf', 2, MovementKind::Return], ['sword', 'container/crate', 7, MovementKind::Return]], array_map(static fn (Movement $movement): array => [$movement->item, $movement->to?->key(), $movement->quantity, $movement->kind], $check->movements));
        self::assertSame(['fair', 'Unloaded', 'event/fair'], [$check->movements[0]->reference, $check->movements[0]->note, $check->movements[0]->from?->key()]);
        self::assertSame([['banner', 1], ['sword', 1]], $this->outstanding('fair'), 'what is missing stays out');

        $later = $this->deployments->checkIn('fair', ['sword' => 1]);
        self::assertSame([['banner', 1, 0]], $this->gaps($later->missing));
        self::assertSame([], $later->surplus);
    }

    public function testMissingPiecesAreWrittenOffWhenAsked(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 3), new LoadLine('lantern', 1)]);
        $this->deployments->deploy('fair');

        $check = $this->deployments->checkIn('fair', ['sword' => 1], true, 'Gone');

        self::assertSame([['lantern', null, 1, MovementKind::Loss], ['sword', 'container/crate', 1, MovementKind::Return], ['sword', null, 2, MovementKind::Loss]], array_map(static fn (Movement $movement): array => [$movement->item, $movement->to?->key(), $movement->quantity, $movement->kind], $check->movements));
        self::assertSame(['fair', 'Gone'], [$check->movements[2]->reference, $check->movements[2]->note]);
        self::assertSame([], $this->outstanding('fair'));
        self::assertSame(8, $this->room->available('sword'));
    }

    public function testAnItemGoesBackToItsHomeBeforeTheContainerOfItsLine(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 2, 'shelf')]);
        $this->deployments->deploy('fair');

        self::assertSame('container/crate', $this->deployments->checkIn('fair', ['sword' => 2])->movements[0]->to?->key());
    }

    public function testEverythingCountedBackAndWrittenOffLosesNothing(): void
    {
        $this->deployments->plan('fair', [new LoadLine('sword', 3)]);
        $this->deployments->deploy('fair');

        $check = $this->deployments->checkIn('fair', ['sword' => 3], true);

        self::assertSame([MovementKind::Return], array_map(static fn (Movement $movement): MovementKind => $movement->kind, $check->movements));
        self::assertSame([], $check->missing);
    }

    public function testAnItemWithoutAHomeGoesBackWhereItsLineTookIt(): void
    {
        $this->deployments->plan('fair', [new LoadLine('banner', 1, 'crate')]);
        $this->deployments->deploy('fair');

        self::assertSame('container/crate', $this->deployments->checkIn('fair', ['banner' => 1])->movements[0]->to?->key());
    }

    public function testAnItemWithNowhereToGoStopsTheCheckBeforeAnyMove(): void
    {
        $this->room->issue('sword', Place::event('fair'), 2);
        $this->room->issue('banner', Place::event('fair'));

        try {
            $this->deployments->checkIn('fair', ['sword' => 2, 'banner' => 1]);
            self::fail('A banner without a home came back.');
        } catch (InvalidStock $invalidStock) {
            self::assertSame('The item "BA" has no home: give it one before it comes back.', $invalidStock->getMessage());
        }

        self::assertSame([['banner', 1], ['sword', 2]], $this->outstanding('fair'));
        self::assertSame([['banner', 1, 0]], $this->gaps($this->deployments->checkIn('fair', ['sword' => 2])->missing), 'a banner not counted needs no home');
    }

    public function testIdentifiersOfDigitsOnlyStayStrings(): void
    {
        $this->room->addContainer('Box', id: '7');
        $this->room->addItem('CA', 'Candle', home: '7', id: '42');
        $this->room->receive('42', '7', 3);

        $this->deployments->plan('99', [new LoadLine('42', 2, '7')]);

        self::assertSame([['42', 'container/7', 2]], $this->moves($this->deployments->deploy('99')->movements));
        self::assertSame([['42', 2, 1]], $this->gaps($this->deployments->checkIn('99', ['42' => 1])->missing));
        self::assertSame(['42' => 1], $this->deployments->checkIn('99', ['42' => 2])->surplus);
    }

    public function testACountIsBounded(): void
    {
        foreach ([-1, 1_000_001] as $count) {
            try {
                $this->deployments->checkIn('fair', ['sword' => $count]);
                self::fail('A count out of bounds was read.');
            } catch (InvalidStock $invalid) {
                self::assertSame(\sprintf('A count is from 0 to 1000000, not %d.', $count), $invalid->getMessage());
            }
        }

        self::assertSame(['sword' => 1_000_000], $this->deployments->checkIn('fair', ['sword' => 1_000_000, 'lantern' => 0])->surplus);
    }

    /**
     * @param list<Movement> $movements
     *
     * @return list<array{string, ?string, int}>
     */
    private function moves(array $movements): array
    {
        return array_map(static fn (Movement $movement): array => [$movement->item, $movement->from?->key(), $movement->quantity], $movements);
    }

    /**
     * @param list<Shortfall> $shortfalls
     *
     * @return list<array{string, int, int}>
     */
    private function gaps(array $shortfalls): array
    {
        return array_map(static fn (Shortfall $gap): array => [$gap->item, $gap->expected, $gap->found], $shortfalls);
    }

    /**
     * @return list<array{string, int}>
     */
    private function outstanding(string $event): array
    {
        return array_map(static fn (Holding $holding): array => [$holding->item, $holding->quantity], $this->deployments->outstanding($event));
    }
}
