<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stock::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(StockRefused::class)]
final class StockAdjustmentTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse'));
        $this->repository->addLocation(Location::of('store', 'Store'));

        $this->stock = new Stock($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
    }

    public function testALevelChangesByADifferenceInTheJournal(): void
    {
        $this->stock->receive('DICE', 'warehouse', 10, $this->at);

        $this->stock->adjust('DICE', 'warehouse', -3, $this->at, 'COUNT-1');
        $this->stock->adjust('DICE', 'warehouse', 5, $this->at);
        $this->stock->adjust('DICE', 'warehouse', -12, $this->at, 'DAMAGED');

        self::assertSame(0, $this->repository->level('DICE', 'warehouse')?->onHand());
        self::assertSame(
            [[MovementKind::Adjusted, -3, 0, 'COUNT-1'], [MovementKind::Adjusted, 5, 0, null], [MovementKind::Adjusted, -12, 0, 'DAMAGED']],
            array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reservedChange, $movement->reference], \array_slice($this->repository->movements, 1)),
        );
    }

    public function testALevelNeverGoesBelowZero(): void
    {
        $this->stock->receive('DICE', 'warehouse', 10, $this->at);

        try {
            $this->stock->adjust('DICE', 'warehouse', -11, $this->at);
            self::fail('The level went below zero.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('DICE at warehouse cannot change by -11: 10 are on hand.', $stockRefused->getMessage());
        }

        self::assertSame(10, $this->repository->level('DICE', 'warehouse')?->onHand());
        self::assertCount(1, $this->repository->movements);
    }

    public function testAnAdjustmentMayLeaveLessOnHandThanIsReserved(): void
    {
        $level = StockLevel::of('DICE', Location::of('warehouse', 'Warehouse'));
        $level->receive(5);
        $level->reserve(4);

        $level->adjust(-3);

        self::assertSame([2, 4, 0], [$level->onHand(), $level->reserved(), $level->available()]);
    }

    public function testANewLevelIsAdjustedUpwards(): void
    {
        $this->stock->adjust('DICE', 'store', 2, $this->at);

        self::assertSame(2, $this->repository->level('DICE', 'store')?->onHand());
    }

    public function testNoDifferenceIsRefusedBeforeAnythingIsLookedUp(): void
    {
        try {
            $this->stock->adjust('DICE', 'store', 0, $this->at);
            self::fail('A difference of zero was journaled.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('A movement moves at least one unit; 0 does not.', $stockRefused->getMessage());
        }

        self::assertNull($this->repository->level('DICE', 'store'));

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('A movement moves at least one unit; 0 does not.');

        StockLevel::of('DICE', Location::of('warehouse', 'Warehouse'))->adjust(0);
    }
}
