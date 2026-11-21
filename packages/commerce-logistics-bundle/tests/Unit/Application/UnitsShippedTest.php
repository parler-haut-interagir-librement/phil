<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Event\ShippedLine;
use Phil\CommerceLogisticsBundle\Domain\Event\UnitsShipped;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stock::class)]
#[CoversClass(UnitsShipped::class)]
#[CoversClass(ShippedLine::class)]
final class UnitsShippedTest extends TestCase
{
    private InMemoryLogistics $repository;

    private RecordingDispatcher $dispatcher;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store', 0));

        $this->dispatcher = new RecordingDispatcher();
        $this->stock = new Stock($this->repository, $this->repository, $this->repository, null, $this->dispatcher);
        $this->at = new DateTimeImmutable('2026-11-03T10:00:00-05:00');
    }

    public function testAShippedOrderIsAnnouncedOnceWithItsUnitsBySkuAndLocation(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);
        $this->stock->receive('MAP', 'warehouse', 2, $this->at);
        $this->stock->reserve($this->order(['DICE' => 4, 'MAP' => 2]), $this->at);

        $this->stock->ship('2026-0001', $this->at);
        $this->stock->ship('2026-0001', $this->at);

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(UnitsShipped::class, $event);
        self::assertSame('2026-0001', $event->orderNumber);
        self::assertSame([['DICE', 'warehouse', 3], ['DICE', 'store', 1], ['MAP', 'warehouse', 2]], $this->lines($event));
        self::assertSame(6, $event->units());
        self::assertSame(['2026-11-03T15:00:00+00:00', 'UTC'], [$event->occurredAt()->format(\DATE_ATOM), $event->occurredAt()->getTimezone()->getName()]);
    }

    public function testAPartialShipmentIsAnnouncedForTheUnitsThatLeft(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);
        $this->stock->reserve($this->order(['DICE' => 6, 'PDF' => 1]), $this->at);

        $this->stock->shipPart('2026-0001', 'DICE', 4, $this->at);
        $this->stock->shipPart('2026-0001', 'PDF', 1, $this->at);
        $this->stock->shipPart('2026-0001', 'DICE', 2, $this->at);

        self::assertSame(
            [[['DICE', 'warehouse', 3], ['DICE', 'store', 1]], [['DICE', 'store', 2]]],
            array_map($this->lines(...), $this->dispatcher->events),
        );
    }

    public function testTheBackorderedUnitsOfALevelLeaveOnOneLine(): void
    {
        $this->stock->receive('DICE', 'warehouse', 2, $this->at);
        $this->stock->allowBackorders('DICE');
        $this->stock->reserve($this->order(['DICE' => 5]), $this->at);
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);

        $this->stock->ship('2026-0001', $this->at);

        self::assertSame([[['DICE', 'warehouse', 5]]], array_map($this->lines(...), $this->dispatcher->events));
    }

    public function testLevelsWhoseNamesRunTogetherAreAnnouncedApart(): void
    {
        $this->repository->addLocation(Location::of('bc', 'BC'));
        $this->repository->addLocation(Location::of('c', 'C'));

        $this->stock->receive('a', 'bc', 1, $this->at);
        $this->stock->receive('ab', 'c', 2, $this->at);
        $this->stock->reserve($this->order(['a' => 1, 'ab' => 2]), $this->at);

        $this->stock->ship('2026-0001', $this->at);

        self::assertSame([[['a', 'bc', 1], ['ab', 'c', 2]]], array_map($this->lines(...), $this->dispatcher->events));
    }

    public function testAnOrderWithNothingHeldIsNotAnnounced(): void
    {
        $this->stock->ship('2026-0001', $this->at);
        $this->stock->shipPart('2026-0001', 'DICE', 1, $this->at);

        self::assertSame([], $this->dispatcher->events);
    }

    public function testTheEventFollowsTheJournal(): void
    {
        $journal = [];
        $stock = new Stock($this->repository, $this->repository, null, null, new RecordingDispatcher(function () use (&$journal): void {
            $journal = array_map(static fn (StockMovement $movement): MovementKind => $movement->kind, $this->repository->movements);
        }));
        $stock->receive('DICE', 'warehouse', 1, $this->at);
        $stock->reserve($this->order(['DICE' => 1]), $this->at);

        $stock->ship('2026-0001', $this->at);

        self::assertSame([MovementKind::Received, MovementKind::Reserved, MovementKind::Shipped], $journal);
    }

    public function testWithoutADispatcherUnitsStillShip(): void
    {
        $stock = new Stock($this->repository, $this->repository);
        $stock->receive('DICE', 'warehouse', 2, $this->at);
        $stock->reserve($this->order(['DICE' => 2]), $this->at);

        $stock->ship('2026-0001', $this->at);

        self::assertSame(0, $this->repository->level('DICE', 'warehouse')?->onHand());
    }

    /**
     * @return list<array{string, string, int}>
     */
    private function lines(object $event): array
    {
        self::assertInstanceOf(UnitsShipped::class, $event);

        return array_map(static fn (ShippedLine $line): array => [$line->sku, $line->location, $line->units], $event->lines);
    }

    /**
     * @param array<string, int> $products
     */
    private function order(array $products): Order
    {
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order = Order::open('2026-0001', Currency::of('CAD'));

        foreach ($products as $id => $quantity) {
            $order->add(Sellable::of(SellableReference::of('product', $id), $id, $cad, 'goods'), $quantity, $cad);
        }

        $order->place($this->at);

        return $order;
    }
}
