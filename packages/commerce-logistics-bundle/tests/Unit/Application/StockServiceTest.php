<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stock::class)]
final class StockServiceTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store', 0));

        $this->stock = new Stock($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-03T10:00:00Z');
    }

    public function testGoodsAreReceivedCountedAndTransferredInTheJournal(): void
    {
        $this->stock->receive('DICE', 'warehouse', 10, $this->at, 'PO-1');
        $this->stock->count('DICE', 'warehouse', 8, $this->at, 'COUNT-1');
        $this->stock->transfer('DICE', 'warehouse', 'store', 3, $this->at, 'TR-1');

        self::assertSame(5, $this->repository->level('DICE', 'warehouse')?->onHand());
        self::assertSame(3, $this->repository->level('DICE', 'store')?->onHand());
        self::assertSame(8, $this->stock->available('DICE'));
        self::assertSame(
            [['warehouse', MovementKind::Received, 10, 0, 'PO-1'], ['warehouse', MovementKind::Counted, -2, 0, 'COUNT-1'], ['warehouse', MovementKind::TransferredOut, -3, 0, 'TR-1'], ['store', MovementKind::TransferredIn, 3, 0, 'TR-1']],
            array_map(static fn (StockMovement $movement): array => [$movement->location, $movement->kind, $movement->onHandChange, $movement->reservedChange, $movement->reference], $this->repository->movements),
        );
    }

    public function testAMovementAtAnUnknownLocationIsRefused(): void
    {
        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('No location is known as "attic".');

        $this->stock->receive('DICE', 'attic', 1, $this->at);
    }

    public function testATransferToTheSameLocationIsRefused(): void
    {
        $this->stock->receive('DICE', 'store', 5, $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('A transfer goes from one location to another, not from store to itself.');

        $this->stock->transfer('DICE', 'store', 'store', 1, $this->at);
    }

    public function testAClosedLocationIsNotAvailable(): void
    {
        $this->stock->receive('DICE', 'warehouse', 10, $this->at);
        $this->stock->receive('DICE', 'store', 2, $this->at);
        $this->repository->findLocation('warehouse')?->close();

        self::assertSame(2, $this->stock->available('DICE'));
        self::assertSame(0, $this->stock->available('MEEPLE'));
    }

    /**
     * The tracked lines are reserved from the locations by priority; untracked lines — never received — are skipped.
     */
    public function testAnOrderIsReservedByPriorityAcrossLocations(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);

        $order = $this->order(['PDF' => 1, 'DICE' => 4], ['gold' => 1]);

        $this->stock->reserve($order, $this->at);

        self::assertSame([['warehouse', 3], ['store', 1]], array_map(static fn (Reservation $reservation): array => [$reservation->location, $reservation->quantity], $this->repository->reservationsOf('1')));
        self::assertSame(4, $this->stock->available('DICE'));
        self::assertSame([[MovementKind::Reserved, 0, 3], [MovementKind::Reserved, 0, 1]], array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->onHandChange, $movement->reservedChange], \array_slice($this->repository->movements, 2)));
        self::assertSame(['1', '1'], array_map(static fn (StockMovement $movement): ?string => $movement->reference, \array_slice($this->repository->movements, 2)));
    }

    public function testTheConfiguredLinesOfOneSkuAreReservedTogether(): void
    {
        $this->stock->receive('SHIRT', 'warehouse', 3, $this->at);

        $this->stock->reserve($this->configured(['Bob' => 2, 'Ann' => 1]), $this->at);

        self::assertSame([['SHIRT', 'warehouse', 3]], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->location, $reservation->quantity], $this->repository->reservationsOf('1')));
        self::assertSame(0, $this->stock->available('SHIRT'));
    }

    public function testTheConfiguredLinesOfOneSkuShareItsStock(): void
    {
        $this->stock->receive('SHIRT', 'warehouse', 3, $this->at);

        try {
            $this->stock->reserve($this->configured(['Bob' => 2, 'Ann' => 2]), $this->at);
            self::fail('The order was reserved.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The order 1 lacks 1 of SHIRT in stock.', $stockRefused->getMessage());
        }

        self::assertSame([], $this->repository->reservations);
        self::assertSame(3, $this->stock->available('SHIRT'));
    }

    public function testAShortageReservesNothing(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('D6', 'store', 1, $this->at);

        try {
            $this->stock->reserve($this->order(['DICE' => 2, 'D6' => 2]), $this->at);
            self::fail('The order was reserved.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The order 1 lacks 1 of D6 in stock.', $stockRefused->getMessage());
        }

        self::assertSame([], $this->repository->reservations);
        self::assertSame(3, $this->stock->available('DICE'));
    }

    public function testAClosedLocationReservesNothing(): void
    {
        $this->stock->receive('DICE', 'warehouse', 10, $this->at);
        $this->stock->receive('DICE', 'store', 2, $this->at);
        $this->repository->findLocation('warehouse')?->close();

        try {
            $this->stock->reserve($this->order(['DICE' => 3]), $this->at);
            self::fail('The order was reserved.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The order 1 lacks 1 of DICE in stock.', $stockRefused->getMessage());
        }

        self::assertSame([], $this->repository->reservations);
        self::assertSame(0, $this->repository->level('DICE', 'warehouse')?->reserved());
    }

    public function testAnEmptyLocationIsSkipped(): void
    {
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->transfer('DICE', 'warehouse', 'store', 1, $this->at);

        $this->stock->reserve($this->order(['DICE' => 1]), $this->at);

        self::assertSame(['store'], array_map(static fn (Reservation $reservation): string => $reservation->location, $this->repository->reservations));
    }

    public function testReservingAnOrderTwiceHoldsItsStockOnce(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(['DICE' => 2]);

        $this->stock->reserve($order, $this->at);
        $this->stock->reserve($order, $this->at);

        self::assertCount(1, $this->repository->reservations);
        self::assertSame(2, $this->repository->level('DICE', 'warehouse')?->reserved());
        self::assertSame(3, $this->stock->available('DICE'));
        self::assertCount(2, $this->repository->movements);
    }

    public function testAReleasedOrderIsNotReservedAgain(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(['DICE' => 2]);
        $this->stock->reserve($order, $this->at);
        $this->stock->release('1', $this->at);

        $this->stock->reserve($order, $this->at);

        self::assertSame(5, $this->stock->available('DICE'));
        self::assertCount(1, $this->repository->reservations);
    }

    public function testAnOrderThatIsNotPlacedIsNotReserved(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $cart = Order::open('7', Currency::of('CAD'));
        $cad = Money::of(1_000, Currency::of('CAD'));
        $cart->add(Sellable::of(SellableReference::of('product', 'DICE'), 'DICE', $cad, 'goods'), 1, $cad);

        try {
            $this->stock->reserve($cart, $this->at);
            self::fail('A cart was reserved.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('The order 7 is not placed: nothing of it is reserved.', $stockRefused->getMessage());
        }

        self::assertSame([], $this->repository->reservations);
    }

    /**
     * One lookup of the levels for the whole order, for its product SKUs only; an order without any looks up nothing.
     */
    public function testReservingLooksTheLevelsUpOncePerOrder(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $this->stock->receive('CUP', 'store', 5, $this->at);
        $this->stock->receive('CUP', 'warehouse', 1, $this->at);

        $this->stock->reserve($this->order(['DICE' => 1, 'CUP' => 2, 'PDF' => 1], ['gold' => 1]), $this->at);

        self::assertSame([['DICE', 'CUP', 'PDF']], $this->repository->levelLookups);
        self::assertSame([['DICE', 'warehouse', 1], ['CUP', 'warehouse', 1], ['CUP', 'store', 1]], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->location, $reservation->quantity], $this->repository->reservations));

        $offers = Order::open('2', Currency::of('CAD'));
        $cad = Money::of(1_000, Currency::of('CAD'));
        $offers->add(Sellable::of(SellableReference::of('offer', 'gold'), 'gold', $cad, 'goods'), 1, $cad);
        $offers->place($this->at);

        $this->stock->reserve($offers, $this->at);

        self::assertCount(1, $this->repository->levelLookups);
    }

    public function testAReleasedOrderGivesItsUnitsBack(): void
    {
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->receive('DICE', 'store', 4, $this->at);
        $this->stock->reserve($this->order(['DICE' => 2]), $this->at);

        $this->stock->release('1', $this->at);

        self::assertSame(5, $this->stock->available('DICE'));
        self::assertSame([ReservationState::Released, ReservationState::Released], array_map(static fn (Reservation $reservation): ReservationState => $reservation->state(), $this->repository->reservations));
        self::assertCount(6, $this->repository->movements);

        $this->stock->release('1', $this->at);
        self::assertCount(6, $this->repository->movements);
        $last = $this->repository->movements[\count($this->repository->movements) - 1];
        self::assertSame([MovementKind::Released, 0, -1], [$last->kind, $last->onHandChange, $last->reservedChange]);
    }

    public function testAShippedOrderLeavesTheShelf(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $this->stock->reserve($this->order(['DICE' => 2]), $this->at);

        $this->stock->ship('1', $this->at);

        self::assertSame([3, 0], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved()]);
        self::assertSame(ReservationState::Shipped, $this->repository->reservations[0]->state());
        $last = $this->repository->movements[\count($this->repository->movements) - 1];
        self::assertSame([MovementKind::Shipped, -2, -2, '1'], [$last->kind, $last->onHandChange, $last->reservedChange, $last->reference]);

        $this->stock->ship('1', $this->at);
        self::assertSame(3, $this->repository->level('DICE', 'warehouse')?->onHand());
    }

    public function testTwoSkusAtOneLocationShipTogether(): void
    {
        $this->stock->receive('DICE', 'store', 5, $this->at);
        $this->stock->receive('MAP', 'store', 5, $this->at);
        $this->stock->reserve($this->order(['DICE' => 5, 'MAP' => 5]), $this->at);

        $this->stock->ship('1', $this->at);

        self::assertSame([0, 0], [$this->repository->level('DICE', 'store')?->onHand(), $this->repository->level('MAP', 'store')?->onHand()]);
    }

    public function testTwoLevelsWhoseNamesRunTogetherAreCheckedApart(): void
    {
        $this->repository->addLocation(Location::of('c', 'C'));
        $this->repository->addLocation(Location::of('bc', 'BC'));

        $this->stock->receive('a', 'bc', 5, $this->at);
        $this->stock->receive('ab', 'c', 5, $this->at);
        $this->stock->reserve($this->order(['a' => 5, 'ab' => 5]), $this->at);

        $this->stock->ship('1', $this->at);

        self::assertSame([0, 0], [$this->repository->level('a', 'bc')?->onHand(), $this->repository->level('ab', 'c')?->onHand()]);
    }

    public function testANumericSkuIsReservedFromItsLevel(): void
    {
        $this->stock->receive('1001', 'store', 3, $this->at);
        $this->stock->reserve($this->order(['1001' => 2]), $this->at);

        self::assertSame([['1001', 'store', 2]], array_map(static fn (Reservation $reservation): array => [$reservation->sku, $reservation->location, $reservation->quantity], $this->repository->reservations));
    }

    /**
     * @param array<int|string, int> $products SKU → quantity
     * @param array<string, int>     $offers   code → quantity
     */
    private function order(array $products, array $offers = []): Order
    {
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));

        foreach ([['product', $products], ['offer', $offers]] as [$type, $lines]) {
            foreach ($lines as $id => $quantity) {
                $order->add(Sellable::of(SellableReference::of($type, (string) $id), (string) $id, $cad, 'goods'), $quantity, $cad);
            }
        }

        $order->place($this->at);

        return $order;
    }

    /**
     * @param array<string, int> $names the name printed on each line of one shirt → quantity
     */
    private function configured(array $names): Order
    {
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));
        $shirt = Sellable::of(SellableReference::of('product', 'SHIRT'), 'Shirt', $cad, 'goods', OptionSet::of(OptionDefinition::text('name', 'Name', TextConstraints::of(20))));

        foreach ($names as $name => $quantity) {
            $order->add($shirt, $quantity, $cad, LineConfiguration::of(['name' => OptionValue::chosen($name)]));
        }

        $order->place($this->at);

        return $order;
    }
}
