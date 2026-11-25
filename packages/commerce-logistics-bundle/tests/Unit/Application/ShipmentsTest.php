<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Shipments;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Delivery\FulfilmentState;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Model\Shipment;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\SwitchableAuthorization;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shipments::class)]
#[CoversClass(Shipment::class)]
#[CoversClass(Stock::class)]
#[CoversClass(Reservation::class)]
#[CoversClass(StockRefused::class)]
final class ShipmentsTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private Shipments $shipments;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store', 0));

        $this->stock = new Stock($this->repository, $this->repository);
        $this->shipments = new Shipments($this->stock, $this->repository, new SwitchableAuthorization());
        $this->at = new DateTimeImmutable('2026-11-03T10:00:00Z');
    }

    public function testAnOrderLeavesInSeveralParcels(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);

        $order = $this->order(['DICE' => 6, 'MAP' => 1], ['gift' => 1]);
        $this->stock->reserve($order, $this->at);

        self::assertSame(FulfilmentState::Unshipped, $this->shipments->state($order));

        $first = $this->shipments->ship($order, $this->at, ['product:DICE' => 4], 'Canada Post', 'CP123');

        self::assertSame(['1', ['product:DICE' => 4], $this->at, 'Canada Post', 'CP123', 4, 0], [$first->orderNumber, $first->lines, $first->shippedAt, $first->carrier, $first->tracking, $first->quantityOf('product:DICE'), $first->quantityOf('product:MAP')]);
        self::assertSame(FulfilmentState::Partial, $this->shipments->state($order));
        self::assertSame(['product:DICE' => 2, 'product:MAP' => 1, 'offer:gift' => 1], $this->shipments->left($order));
        self::assertSame([0, 0], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved()]);
        self::assertSame([4, 2], [$this->repository->level('DICE', 'store')?->onHand(), $this->repository->level('DICE', 'store')?->reserved()]);
        self::assertSame([ReservationState::Shipped, ReservationState::Held, 2], [$this->repository->reservations[0]->state(), $this->repository->reservations[1]->state(), $this->repository->reservations[1]->remaining()]);
        $movements = \array_slice($this->repository->movements, -2);
        self::assertSame([[MovementKind::Shipped, 'warehouse', -3, -3, '1'], [MovementKind::Shipped, 'store', -1, -1, '1']], array_map(static fn (StockMovement $movement): array => [$movement->kind, $movement->location, $movement->onHandChange, $movement->reservedChange, $movement->reference], $movements));

        $second = $this->shipments->ship($order, $this->at);

        self::assertSame(['product:DICE' => 2, 'product:MAP' => 1, 'offer:gift' => 1], $second->lines);
        self::assertSame([null, null], [$second->carrier, $second->tracking]);
        self::assertSame(FulfilmentState::Shipped, $this->shipments->state($order));
        self::assertSame([2, 0, ReservationState::Shipped], [$this->repository->level('DICE', 'store')?->onHand(), $this->repository->level('DICE', 'store')?->reserved(), $this->repository->reservations[1]->state()]);
    }

    public function testOnlyTheLinesOfTheShipmentLeaveTheShelf(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 3, $this->at);
        $this->stock->receive('CUP', 'warehouse', 5, $this->at);

        $order = $this->order(['DICE' => 6, 'CUP' => 2], ['DICE' => 1]);
        $this->stock->reserve($order, $this->at);

        $this->shipments->ship($order, $this->at, ['offer:DICE' => 1]);
        $this->shipments->ship($order, $this->at, ['product:DICE' => 2]);

        self::assertSame([1, 1, 3, 3, 5, 2], [
            $this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved(),
            $this->repository->level('DICE', 'store')?->onHand(), $this->repository->level('DICE', 'store')?->reserved(),
            $this->repository->level('CUP', 'warehouse')?->onHand(), $this->repository->level('CUP', 'warehouse')?->reserved(),
        ]);

        $this->shipments->ship($order, $this->at, ['product:CUP' => 2]);
        $rest = $this->shipments->ship($order, $this->at);

        self::assertSame(['product:DICE' => 4], $rest->lines);
        self::assertSame(FulfilmentState::Shipped, $this->shipments->state($order));
    }

    public function testShippingTheRestReleasesNothingTwice(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(['DICE' => 4]);
        $this->stock->reserve($order, $this->at);
        $this->shipments->ship($order, $this->at, ['product:DICE' => 1]);

        $this->stock->ship('1', $this->at);
        self::assertSame([1, 0], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved()]);
        $last = $this->repository->movements[\count($this->repository->movements) - 1];
        self::assertSame([-3, -3], [$last->onHandChange, $last->reservedChange]);
    }

    public function testReleasingAfterAPartGivesBackTheRest(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(['DICE' => 4]);
        $this->stock->reserve($order, $this->at);
        $this->shipments->ship($order, $this->at, ['product:DICE' => 1]);

        $this->stock->release('1', $this->at);

        self::assertSame([4, 0, ReservationState::Released], [$this->repository->level('DICE', 'warehouse')?->onHand(), $this->repository->level('DICE', 'warehouse')?->reserved(), $this->repository->reservations[0]->state()]);
        $last = $this->repository->movements[\count($this->repository->movements) - 1];
        self::assertSame([MovementKind::Released, 0, -3], [$last->kind, $last->onHandChange, $last->reservedChange]);
    }

    public function testAnUntrackedProductShipsWithoutStock(): void
    {
        $order = $this->order(['MAP' => 2]);

        $this->shipments->ship($order, $this->at, ['product:MAP' => 1]);

        self::assertSame(0, $this->stock->shipPart('1', 'MAP', 1, $this->at));
        self::assertSame([], $this->repository->movements);
    }

    public function testMoreThanIsHeldIsRefused(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $this->stock->reserve($this->order(['DICE' => 2]), $this->at);

        try {
            $this->stock->shipPart('1', 'DICE', 3, $this->at);
            self::fail('More than held was shipped.');
        } catch (StockRefused $stockRefused) {
            self::assertSame(StockRefused::shortage('1', 'DICE', 1)->getMessage(), $stockRefused->getMessage());
        }

        self::assertSame(2, $this->stock->shipPart('1', 'DICE', 2, $this->at));
    }

    public function testOnlyWhatIsLeftOfTheOrdersLinesShips(): void
    {
        $this->stock->receive('DICE', 'warehouse', 5, $this->at);
        $order = $this->order(['DICE' => 2]);
        $this->stock->reserve($order, $this->at);
        $this->shipments->ship($order, $this->at, ['product:DICE' => 1]);

        foreach ([
            [['product:DICE' => 2], '2 of product:DICE cannot ship: 1 are left to ship.'],
            [['product:CUP' => 1], 'The order 1 has no line product:CUP.'],
        ] as [$lines, $message]) {
            try {
                $this->shipments->ship($order, $this->at, $lines);
                self::fail($message);
            } catch (StockRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        self::assertCount(1, $this->repository->shipments);
    }

    public function testOnlyAPlacedOrderShips(): void
    {
        $order = Order::open('9', Currency::of('CAD'));

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 9 is not placed: nothing of it ships.');

        $this->shipments->ship($order, $this->at);
    }

    public function testAPartOfAReservationIsChecked(): void
    {
        $level = StockLevel::of('DICE', Location::of('warehouse', 'Warehouse', 10));
        $level->receive(5);

        $reservation = Reservation::hold('1', $level, 3);

        foreach ([0, 4] as $quantity) {
            try {
                $reservation->shipPart($level, $quantity);
                self::fail('A bad part was shipped.');
            } catch (StockRefused $refused) {
                self::assertSame(StockRefused::quantity($quantity)->getMessage(), $refused->getMessage());
            }
        }

        $reservation->shipPart($level, 3);
        self::assertSame([ReservationState::Shipped, 0, 2], [$reservation->state(), $reservation->remaining(), $level->onHand()]);
    }

    /**
     * @return iterable<string, array{array<string, int>, ?string, ?string, string}>
     */
    public static function badShipments(): iterable
    {
        yield 'no line' => [[], null, null, 'A shipment of 1 holds at least one line.'];
        yield 'no unit' => [['product:A' => 0], null, null, 'A shipment holds one unit of product:A or more; 0 is refused.'];
        yield 'an empty carrier' => [['product:A' => 1], '', null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'a long carrier' => [['product:A' => 1], str_repeat('c', 65), null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'a carrier and a newline' => [['product:A' => 1], "UPS\n", null, 'A carrier is named in 1 to 64 printable ASCII characters.'];
        yield 'a spaced tracking' => [['product:A' => 1], null, 'CP 1', 'A tracking number is 1 to 128 visible ASCII characters.'];
        yield 'a long tracking' => [['product:A' => 1], null, str_repeat('t', 129), 'A tracking number is 1 to 128 visible ASCII characters.'];
        yield 'a tracking and a newline' => [['product:A' => 1], null, "CP1\n", 'A tracking number is 1 to 128 visible ASCII characters.'];
    }

    /**
     * @param array<string, int> $lines
     */
    #[DataProvider('badShipments')]
    public function testAShipmentIsChecked(array $lines, ?string $carrier, ?string $tracking, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Shipment::of('1', $lines, $this->at, $carrier, $tracking);
    }

    public function testACarrierMayHoldSpaces(): void
    {
        self::assertSame('Canada Post', Shipment::of('1', ['product:A' => 1], $this->at, 'Canada Post')->carrier);
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame([
            'The order 1 is not placed: nothing of it ships.',
            'The order 1 has no line product:A.',
            '2 of product:A cannot ship: 1 are left to ship.',
        ], [StockRefused::notShippable('1')->getMessage(), StockRefused::notInOrder('1', 'product:A')->getMessage(), StockRefused::overShipped('product:A', 2, 1)->getMessage()]);
    }

    /**
     * @param array<string, int> $products SKU → quantity
     * @param array<string, int> $offers   code → quantity
     */
    private function order(array $products, array $offers = []): Order
    {
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('1', Currency::of('CAD'));

        foreach ([['product', $products], ['offer', $offers]] as [$type, $lines]) {
            foreach ($lines as $id => $quantity) {
                $order->add(Sellable::of(SellableReference::of($type, $id), $id, $cad, 'goods'), $quantity, $cad);
            }
        }

        $order->place($this->at);

        return $order;
    }
}
