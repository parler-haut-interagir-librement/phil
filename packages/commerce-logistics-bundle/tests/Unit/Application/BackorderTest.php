<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Model\StockPolicy;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stock::class)]
#[CoversClass(StockPolicy::class)]
#[CoversClass(StockLevel::class)]
#[CoversClass(Reservation::class)]
final class BackorderTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store'));

        $this->stock = new Stock($this->repository, $this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-12T10:00:00Z');
    }

    public function testASkuSoldWithoutStockReservesItsShortfallAtItsFirstOpenLocation(): void
    {
        $this->stock->receive('DICE', 'store', 2, $this->at);
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->allowBackorders('DICE');

        self::assertTrue($this->stock->allowsBackorders('DICE'));
        $this->stock->reserve($this->order(7), $this->at);

        self::assertSame(
            [['warehouse', 1], ['store', 2], ['warehouse', 4]],
            array_map(static fn (Reservation $reservation): array => [$reservation->location, $reservation->quantity], $this->repository->reservations),
        );
        self::assertSame(
            [MovementKind::Reserved, MovementKind::Reserved, MovementKind::Backordered],
            array_map(static fn (StockMovement $movement): MovementKind => $movement->kind, \array_slice($this->repository->movements, 2)),
        );
        self::assertSame([0, 4], [$this->stock->available('DICE'), $this->stock->backordered('DICE')]);
    }

    public function testABackorderedUnitLeavesOnceReceived(): void
    {
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->allowBackorders('DICE');
        $this->stock->reserve($this->order(3), $this->at);

        try {
            $this->stock->ship('2026-0001', $this->at);
            self::fail('Units not on hand left.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('3 of DICE cannot leave warehouse: 1 are on hand.', $stockRefused->getMessage());
        }

        self::assertSame([1, 3], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved()]);

        try {
            $this->stock->shipPart('2026-0001', 'DICE', 2, $this->at);
            self::fail('Units not on hand left.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('2 of DICE cannot leave warehouse: 1 are on hand.', $stockRefused->getMessage());
        }

        self::assertSame(1, $this->stock->shipPart('2026-0001', 'DICE', 1, $this->at));

        $this->stock->receive('DICE', 'warehouse', 2, $this->at, 'PO-9');
        self::assertSame(0, $this->stock->backordered('DICE'));
        $this->stock->ship('2026-0001', $this->at);
        self::assertSame(0, $this->stock->backordered('DICE'));
        self::assertSame([0, 0], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved()]);
    }

    public function testAForbiddenBackorderRefusesTheShortage(): void
    {
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->allowBackorders('DICE');
        $this->stock->forbidBackorders('DICE');

        self::assertFalse($this->stock->allowsBackorders('DICE'));
        self::assertCount(1, $this->repository->policies);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 lacks 1 of DICE in stock.');

        $this->stock->reserve($this->order(2), $this->at);
    }

    public function testWithoutPoliciesNothingSellsWithoutStock(): void
    {
        $stock = new Stock($this->repository, $this->repository);

        self::assertFalse($stock->allowsBackorders('DICE'));

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('Backorders are set through a StockPolicyRepository: none is wired.');

        $stock->allowBackorders('DICE');
    }

    public function testAPolicyNamesAValidSku(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"bad sku" is not a SKU');

        StockPolicy::of('bad sku');
    }

    public function testASkuWithoutPolicyDoesNotSellWithoutStock(): void
    {
        $this->stock->allowBackorders('DICE');

        self::assertFalse($this->stock->allowsBackorders('MAP'));
    }

    public function testEachSkuSoldWithoutStockHoldsItsOwnShortfall(): void
    {
        $this->stock->allowBackorders('DICE');
        $this->stock->allowBackorders('MAP');
        $this->stock->allowBackorders('BAG');
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->receive('MAP', 'store', 1, $this->at);
        $this->stock->receive('BAG', 'store', 1, $this->at);
        $this->stock->reserve($this->orderOf(['DICE' => 3, 'MAP' => 2, 'BAG' => 1]), $this->at);

        self::assertSame(
            [['DICE', 'warehouse', 1], ['DICE', 'warehouse', 2], ['MAP', 'store', 1], ['MAP', 'store', 1], ['BAG', 'store', 1]],
            array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->location, $reservation->quantity], $this->repository->reservations),
        );
        self::assertSame([2, 1, 0], [$this->stock->backordered('DICE'), $this->stock->backordered('MAP'), $this->stock->backordered('BAG')]);
    }

    public function testANumericSkuShortageNamesItsSku(): void
    {
        $this->stock->receive('1001', 'warehouse', 1, $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 lacks 1 of 1001 in stock.');

        $this->stock->reserve($this->orderOf(['1001' => 2]), $this->at);
    }

    public function testABackorderAddsToWhatIsAlreadyReserved(): void
    {
        $level = StockLevel::of('DICE', Location::of('warehouse', 'Warehouse'));
        $level->receive(5);
        $level->reserve(2);
        $level->backorder(4);

        self::assertSame([6, 1], [$level->reserved(), $level->backordered()]);
    }

    /**
     * @param array<int|string, int> $lines
     */
    private function orderOf(array $lines): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));

        foreach ($lines as $sku => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', (string) $sku), (string) $sku, $price, 'goods'), $quantity, $price);
        }

        $order->place($this->at);

        return $order;
    }

    private function order(int $quantity): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), $quantity, $price);
        $order->place($this->at);

        return $order;
    }
}
