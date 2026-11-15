<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use Closure;
use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
#[CoversClass(OrderLocked::class)]
final class OrderStateTest extends TestCase
{
    public function testAnOpenedOrderIsACart(): void
    {
        $order = Order::open('1', Currency::of('CAD'));

        self::assertSame(OrderState::Cart, $order->state());
        self::assertNull($order->placedAt());
        self::assertNull($order->cancelledAt());
        self::assertSame(1, $order->version());
    }

    public function testAPlacedOrderKeepsItsMoment(): void
    {
        $order = $this->cart();
        $at = new DateTimeImmutable('2026-11-03T15:00:00Z');

        $order->place($at);

        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($at, $order->placedAt());
        self::assertSame(1_000, $order->total()->minorUnits);
    }

    public function testAnEmptyCartCannotBePlaced(): void
    {
        $order = Order::open('7', Currency::of('CAD'));

        try {
            $order->place(new DateTimeImmutable());
            self::fail('An empty cart was placed.');
        } catch (OrderLocked $orderLocked) {
            self::assertSame('The order 7 has no line: an empty cart cannot be placed.', $orderLocked->getMessage());
        }

        self::assertSame(OrderState::Cart, $order->state());
        self::assertNull($order->placedAt());
    }

    public function testACartOrAPlacedOrderIsCancelled(): void
    {
        $at = new DateTimeImmutable('2026-11-04T09:00:00Z');
        $cart = $this->cart();
        $cart->cancel($at);

        self::assertSame(OrderState::Cancelled, $cart->state());
        self::assertSame($at, $cart->cancelledAt());

        $placed = $this->cart();
        $placed->place(new DateTimeImmutable('2026-11-03T15:00:00Z'));
        $placed->cancel($at);

        self::assertSame(OrderState::Cancelled, $placed->state());
        self::assertSame($at, $placed->cancelledAt());
        self::assertNotNull($placed->placedAt());
    }

    /**
     * @return iterable<string, array{Closure(Order): void, string}>
     */
    public static function refusedTransitions(): iterable
    {
        $at = new DateTimeImmutable();

        yield 'placed again' => [static function (Order $order) use ($at): void {
            $order->place($at);
            $order->place($at);
        }, 'The order 1 is placed: it cannot become placed.'];
        yield 'cancelled again' => [static function (Order $order) use ($at): void {
            $order->cancel($at);
            $order->cancel($at);
        }, 'The order 1 is cancelled: it cannot become cancelled.'];
        yield 'placed once cancelled' => [static function (Order $order) use ($at): void {
            $order->cancel($at);
            $order->place($at);
        }, 'The order 1 is cancelled: it cannot become placed.'];
    }

    /**
     * @param Closure(Order): void $moves
     */
    #[DataProvider('refusedTransitions')]
    public function testAStateMovesOnlyAlongItsTransitions(Closure $moves, string $message): void
    {
        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains($message);

        $moves($this->cart());
    }

    /**
     * @return iterable<string, array{Closure(Order): mixed}>
     */
    public static function changes(): iterable
    {
        $reference = SellableReference::of('product', 'dice');
        $cad = Money::of(1_000, Currency::of('CAD'));

        yield 'add' => [static fn (Order $order): OrderLine => $order->add(Sellable::of($reference, 'Dice', $cad, 'goods'), 1, $cad)];
        yield 'change a quantity' => [static fn (Order $order) => $order->changeQuantity($reference, 2, $cad)];
        yield 'remove' => [static fn (Order $order) => $order->remove($reference)];
        yield 'change the tax zone' => [static fn (Order $order) => $order->changeTaxZone('QC')];
        yield 'change the channel' => [static fn (Order $order) => $order->changeChannel('web')];
        yield 'change the customer group' => [static fn (Order $order) => $order->changeCustomerGroup('vip')];
        yield 'choose a delivery method' => [static fn (Order $order) => $order->chooseDeliveryMethod('post')];
        yield 'enter a code' => [static fn (Order $order) => $order->enterCode('SAVE10')];
        yield 'remove a code' => [static fn (Order $order) => $order->removeCode('SAVE10')];
        yield 'replace adjustments' => [static fn (Order $order) => $order->replaceAdjustments(AdjustmentType::Fee, [Adjustment::of(AdjustmentType::Fee, 'Fee', $cad)])];
    }

    /**
     * @param Closure(Order): mixed $change
     */
    #[DataProvider('changes')]
    public function testAPlacedOrderNoLongerChanges(Closure $change): void
    {
        $order = $this->cart();
        $order->place(new DateTimeImmutable());

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 1 is placed: its lines and adjustments no longer change.');

        $change($order);
    }

    /**
     * @param Closure(Order): mixed $change
     */
    #[DataProvider('changes')]
    public function testACancelledOrderNoLongerChanges(Closure $change): void
    {
        $order = $this->cart();
        $order->cancel(new DateTimeImmutable());

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 1 is cancelled: its lines and adjustments no longer change.');

        $change($order);
    }

    public function testALockIsADomainError(): void
    {
        self::assertInstanceOf(DomainError::class, OrderLocked::empty('1'));
    }

    private function cart(): Order
    {
        $order = Order::open('1', Currency::of('CAD'));
        $cad = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $cad, 'goods'), 1, $cad);

        return $order;
    }
}
