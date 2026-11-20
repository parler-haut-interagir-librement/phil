<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceLogisticsBundle\Application\SerialNumbers;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\SerialNumber;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Serial\SerialState;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SerialNumbers::class)]
#[CoversClass(SerialNumber::class)]
#[CoversClass(StockLevel::class)]
final class SerialNumbersTest extends TestCase
{
    private InMemoryLogistics $repository;

    private SerialNumbers $serials;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse'));
        $this->repository->addLocation(Location::of('store', 'Store'));

        $this->serials = new SerialNumbers($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-12T10:00:00Z');
        $stock = new Stock($this->repository, $this->repository);
        $stock->receive('CONSOLE', 'warehouse', 3, $this->at);
        $stock->receive('CONSOLE', 'store', 1, $this->at);
    }

    public function testUnitsAreRegisteredMovedShippedAndRestocked(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1', 'SN-2'], $this->at);
        $this->serials->move('CONSOLE', 'warehouse', 'store', ['SN-2']);
        $this->serials->ship('2026-0001', 'CONSOLE', ['SN-1'], $this->at);

        $shipped = $this->serials->find('CONSOLE', 'SN-1');
        self::assertSame([SerialState::Shipped, '2026-0001', $this->at, 'warehouse'], [$shipped?->state(), $shipped?->orderNumber(), $shipped?->shippedAt(), $shipped?->location()]);
        self::assertSame('store', $this->serials->find('CONSOLE', 'SN-2')?->location());
        self::assertSame(['SN-1'], array_map(static fn (SerialNumber $serial): string => $serial->serial, $this->serials->ofOrder('2026-0001')));

        $this->serials->restock('CONSOLE', 'SN-1', 'store');
        self::assertSame([SerialState::InStock, null, null, 'store'], [$shipped?->state(), $shipped?->orderNumber(), $shipped?->shippedAt(), $shipped?->location()]);
        self::assertSame(2, $this->repository->serialsInStock('CONSOLE', 'store'));
    }

    public function testNoMoreUnitsAreRegisteredThanAreOnHand(): void
    {
        $this->serials->register('CONSOLE', 'store', ['SN-9'], $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('2 serialised units of CONSOLE at store would exceed the 1 on hand.');

        $this->serials->register('CONSOLE', 'store', ['SN-10'], $this->at);
    }

    public function testASerialIsRegisteredOnce(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1'], $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The unit SN-1 of CONSOLE is already registered.');

        $this->serials->register('CONSOLE', 'store', ['SN-1'], $this->at);
    }

    public function testAUnitShippedOnceDoesNotShipAgainNorMove(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1'], $this->at);
        $this->serials->ship('2026-0001', 'CONSOLE', ['SN-1'], $this->at);

        try {
            $this->serials->move('CONSOLE', 'warehouse', 'store', ['SN-1']);
            self::fail('A shipped unit moved.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The unit SN-1 of CONSOLE is not in stock.', $stockRefused->getMessage());
        }

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The unit SN-1 of CONSOLE is not in stock.');

        $this->serials->ship('2026-0002', 'CONSOLE', ['SN-1'], $this->at);
    }

    public function testAUnitInStockIsNotRestocked(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1'], $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The unit SN-1 of CONSOLE is already in stock.');

        $this->serials->restock('CONSOLE', 'SN-1', 'warehouse');
    }

    public function testAUnitMovesFromWhereItIs(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1'], $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The unit SN-1 of CONSOLE is not at store.');

        $this->serials->move('CONSOLE', 'store', 'warehouse', ['SN-1']);
    }

    public function testAnUnknownUnitOrLevelIsRefused(): void
    {
        try {
            $this->serials->ship('2026-0001', 'CONSOLE', ['SN-404'], $this->at);
            self::fail('An unknown unit shipped.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('No unit SN-404 of CONSOLE is known.', $stockRefused->getMessage());
        }

        $this->expectException(StockRefused::class);
        $this->serials->register('CONSOLE', 'attic', ['SN-1'], $this->at);
    }

    public function testABatchNamesEachSerialOnceAndNotTooMany(): void
    {
        try {
            $this->serials->register('CONSOLE', 'warehouse', ['SN-1', 'SN-1'], $this->at);
            self::fail('A serial was named twice.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A call names 1 to 1000 serial numbers, each once.', $invalidValue->getMessage());
        }

        try {
            $this->serials->ship('2026-0001', 'CONSOLE', [], $this->at);
            self::fail('An empty batch shipped.');
        } catch (InvalidValue) {
        }

        $this->expectException(InvalidValue::class);
        $this->serials->ship('2026-0001', 'CONSOLE', array_map(static fn (int $serial): string => 'SN-'.$serial, range(1, 1_001)), $this->at);
    }

    public function testABatchOfExactlyTheMostSerialsIsAccepted(): void
    {
        try {
            $this->serials->ship('2026-0001', 'CONSOLE', array_map(static fn (int $serial): string => 'SN-'.$serial, range(1, 1_000)), $this->at);
            self::fail('Unknown units shipped.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('No unit SN-1 of CONSOLE is known.', $stockRefused->getMessage());
        }
    }

    public function testAMoveNamesEachSerialOnce(): void
    {
        $this->serials->register('CONSOLE', 'warehouse', ['SN-1'], $this->at);

        foreach ([[], ['SN-1', 'SN-1']] as $batch) {
            try {
                $this->serials->move('CONSOLE', 'warehouse', 'store', $batch);
                self::fail('A bad batch moved.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('A call names 1 to 1000 serial numbers, each once.', $invalidValue->getMessage());
            }
        }

        self::assertSame('warehouse', $this->serials->find('CONSOLE', 'SN-1')?->location());
    }

    public function testAMissingLevelAsksForOneUnitAndHasNone(): void
    {
        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('1 of CONSOLE are asked at attic; 0 are available.');

        $this->serials->register('CONSOLE', 'attic', ['SN-1'], $this->at);
    }

    public function testASerialIsVisibleAscii(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A serial number is 1 to 128 visible ASCII characters.');

        $this->serials->register('CONSOLE', 'warehouse', ['SN 1'], $this->at);
    }
}
