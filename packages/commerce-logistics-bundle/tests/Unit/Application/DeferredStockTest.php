<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\DeferredStock;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\StockRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\Location;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Stock\ReservationState;
use Phil\CommerceLogisticsBundle\Tests\Unit\Support\InMemoryLogistics;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(DeferredStock::class)]
final class DeferredStockTest extends TestCase
{
    public function testTheStockOfADeferredSaleIsHeldThenFreed(): void
    {
        $repository = new InMemoryLogistics();
        $repository->addLocation(Location::of('warehouse', 'Warehouse'));

        $stock = new Stock($repository, $repository);
        $clock = new MockClock('2026-11-12 10:00:00');
        $stock->receive('DICE', 'warehouse', 5, $clock->now());

        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'DICE'), 'Dice', $price, 'goods'), 2, $price);
        $order->place($clock->now());

        $orders = new class([$order]) implements OrderRepository {
            /** @param list<Order> $orders */
            public function __construct(private array $orders)
            {
            }

            #[Override]
            public function find(string $number): ?Order
            {
                foreach ($this->orders as $order) {
                    if ($order->number === $number) {
                        return $order;
                    }
                }

                return null;
            }

            #[Override]
            public function add(Order $order): void
            {
                $this->orders[] = $order;
            }
        };
        $commitments = new DeferredStock($orders, $stock, $clock);

        $commitments->commit('2026-0001');
        $commitments->commit('2026-0001');
        self::assertSame(3, $stock->available('DICE'));
        self::assertSame('2026-11-12 10:00', $repository->movements[1]->movedAt->format('Y-m-d H:i'));

        $clock->modify('+1 day');
        $commitments->release('2026-0001');
        self::assertSame(5, $stock->available('DICE'));
        self::assertSame([ReservationState::Released], array_map(static fn (Reservation $reservation): ReservationState => $reservation->state(), $repository->reservations));
        self::assertSame('2026-11-13 10:00', $repository->movements[2]->movedAt->format('Y-m-d H:i'));

        $this->expectException(StockRefused::class);
        $this->expectExceptionMessageIsOrContains('No order is known as "2026-0404".');

        $commitments->commit('2026-0404');
    }
}
