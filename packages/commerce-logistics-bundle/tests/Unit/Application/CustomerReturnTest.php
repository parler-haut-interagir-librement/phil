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
use Phil\CommerceLogisticsBundle\Domain\Model\StockMovement;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stock::class)]
#[CoversClass(Reservation::class)]
#[CoversClass(StockRefused::class)]
final class CustomerReturnTest extends TestCase
{
    private InMemoryLogistics $repository;

    private Stock $stock;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->repository = new InMemoryLogistics();
        $this->repository->addLocation(Location::of('warehouse', 'Warehouse', 10));
        $this->repository->addLocation(Location::of('store', 'Store', 0));
        $this->repository->addLocation(Location::of('returns', 'Returns', 0));

        $this->stock = new Stock($this->repository, $this->repository);
        $this->at = new DateTimeImmutable('2026-11-21T10:00:00Z');
    }

    public function testReturnedUnitsGoBackOnAShelfUnderTheOrdersNumber(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at, 'PO-1');
        $this->stock->reserve($this->order(['DICE' => 3]), $this->at);
        $this->stock->ship('2026-0001', $this->at);

        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'returns', 2, $this->at);
        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $this->at);

        self::assertSame([2, 1], [$this->repository->level('DICE', 'returns')?->onHand(), $this->repository->level('DICE', 'warehouse')?->onHand()]);
        self::assertSame(
            [['returns', MovementKind::Returned, 2, 0, '2026-0001'], ['warehouse', MovementKind::Returned, 1, 0, '2026-0001']],
            array_map(
                static fn (StockMovement $movement): array => [$movement->location, $movement->kind, $movement->onHandChange, $movement->reservedChange, $movement->reference],
                \array_slice($this->repository->movements, 3),
            ),
        );
        self::assertSame([[3, 3, 0]], $this->counts());
    }

    public function testNoMoreComesBackThanLeft(): void
    {
        $this->stock->receive('DICE', 'warehouse', 4, $this->at);
        $this->stock->reserve($this->order(['DICE' => 4]), $this->at);
        $this->stock->shipPart('2026-0001', 'DICE', 2, $this->at);
        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $this->at);

        $journal = \count($this->repository->movements);

        try {
            $this->stock->returnFromCustomer('2026-0001', 'DICE', 'returns', 2, $this->at);
            self::fail('Two units came back where one could.');
        } catch (StockRefused $stockRefused) {
            self::assertSame('2 of DICE cannot come back from the order 2026-0001: 1 left with it and were not returned yet.', $stockRefused->getMessage());
        }

        self::assertCount($journal, $this->repository->movements);
        self::assertNull($this->repository->level('DICE', 'returns'));
        self::assertSame([[2, 1, 1]], $this->counts());
    }

    public function testAReturnSpreadsOverTheOrdersReservationsInTheirOrder(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->receive('DICE', 'store', 5, $this->at);
        $this->stock->receive('MAP', 'warehouse', 1, $this->at);
        $this->stock->reserve($this->order(['DICE' => 5, 'MAP' => 1]), $this->at);
        $this->stock->ship('2026-0001', $this->at);

        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'returns', 4, $this->at);

        self::assertSame([[3, 3, 0], [2, 1, 1], [1, 0, 1]], $this->counts());
        self::assertSame(4, $this->repository->level('DICE', 'returns')?->onHand());

        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'returns', 1, $this->at);

        self::assertSame([[3, 3, 0], [2, 2, 0], [1, 0, 1]], $this->counts());
    }

    public function testAnotherSkuOfTheOrderDoesNotCount(): void
    {
        $this->stock->receive('DICE', 'warehouse', 1, $this->at);
        $this->stock->receive('MAP', 'warehouse', 5, $this->at);
        $this->stock->reserve($this->order(['DICE' => 1, 'MAP' => 5]), $this->at);
        $this->stock->ship('2026-0001', $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('2 of DICE cannot come back from the order 2026-0001: 1 left with it');

        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 2, $this->at);
    }

    public function testAnOrderThatShippedNothingTakesNothingBack(): void
    {
        $this->stock->receive('DICE', 'warehouse', 2, $this->at);
        $this->stock->reserve($this->order(['DICE' => 2]), $this->at);

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('1 of DICE cannot come back from the order 2026-0001: 0 left with it');

        $this->stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', 1, $this->at);
    }

    #[DataProvider('noUnits')]
    public function testAReturnBringsBackOneUnitOrMore(int $quantity): void
    {
        try {
            $this->stock->returnFromCustomer('2026-0001', 'DICE', 'warehouse', $quantity, $this->at);
            self::fail(\sprintf('%d units came back.', $quantity));
        } catch (StockRefused $stockRefused) {
            self::assertSame(\sprintf('A movement moves at least one unit; %d does not.', $quantity), $stockRefused->getMessage());
        }

        self::assertNull($this->repository->level('DICE', 'warehouse'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function noUnits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function testAReservationCountsBackOnlyWhatLeft(): void
    {
        $this->stock->receive('DICE', 'warehouse', 3, $this->at);
        $this->stock->reserve($this->order(['DICE' => 3]), $this->at);
        $this->stock->shipPart('2026-0001', 'DICE', 2, $this->at);

        $reservation = $this->repository->reservationsOf('2026-0001')[0];

        foreach ([[0, 'A movement moves at least one unit; 0 does not.'], [3, '3 of DICE cannot come back from the order 2026-0001: 2 left with it and were not returned yet.']] as [$quantity, $message]) {
            try {
                $reservation->returnPart($quantity);
                self::fail(\sprintf('%d units came back.', $quantity));
            } catch (StockRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }

        $reservation->returnPart(2);
        self::assertSame([2, 2, 0, 1], [$reservation->shipped(), $reservation->returned(), $reservation->returnable(), $reservation->remaining()]);
    }

    /**
     * @return list<array{int, int, int}> shipped, returned and still returnable, per reservation of the order
     */
    private function counts(): array
    {
        return array_map(
            static fn (Reservation $reservation): array => [$reservation->shipped(), $reservation->returned(), $reservation->returnable()],
            $this->repository->reservationsOf('2026-0001'),
        );
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
