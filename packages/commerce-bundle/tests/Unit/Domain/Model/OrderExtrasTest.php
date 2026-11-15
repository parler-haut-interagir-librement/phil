<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Exception\LineNotInOrder;
use Phil\CommerceBundle\Domain\Exception\OrderLocked;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\GiftOptions;
use Phil\CommerceBundle\Domain\ValueObject\ManualDiscount;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What an order keeps beside its lines: its customer, its gift options, its wish list, its manual discounts, the
 * application's status, its revision, its expiry, and a forced move.
 */
#[CoversClass(Order::class)]
#[CoversClass(OrderLocked::class)]
final class OrderExtrasTest extends TestCase
{
    public function testACartKeepsItsCustomerGiftAndWishList(): void
    {
        $order = $this->cart();
        self::assertNull($order->customer());
        self::assertNull($order->giftOptions());
        self::assertNull($order->wishList());

        $order->assignCustomer('customer-1');
        $order->offerAsGift(GiftOptions::of(true, message: 'Enjoy'));
        $order->buyFromWishList('0190a0b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b');

        self::assertSame('customer-1', $order->customer());
        self::assertEquals(GiftOptions::of(true, message: 'Enjoy'), $order->giftOptions());
        self::assertSame('0190a0b2-3c4d-7e5f-8a9b-0c1d2e3f4a5b', $order->wishList());

        $order->assignCustomer(null);
        $order->offerAsGift(null);
        $order->buyFromWishList(null);

        self::assertNull($order->customer());
        self::assertNull($order->giftOptions());
        self::assertNull($order->wishList());
    }

    public function testAWishListIsNamedByItsId(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"list-1" is not the id of a wish list.');

        $this->cart()->buyFromWishList('list-1');
    }

    public function testACustomerIsAStableIdentifier(): void
    {
        $this->expectException(InvalidValue::class);

        $this->cart()->assignCustomer('bob@example.com');
    }

    /**
     * @return iterable<string, array{callable(Order): void}>
     */
    public static function cartOnly(): iterable
    {
        yield 'customer' => [static fn (Order $order) => $order->assignCustomer('c')];
        yield 'gift' => [static fn (Order $order) => $order->offerAsGift(null)];
        yield 'wish list' => [static fn (Order $order) => $order->buyFromWishList(null)];
        yield 'grant a discount' => [static fn (Order $order) => $order->grantManualDiscount(ManualDiscount::share('m', 100, 'R', 'e'))];
        yield 'withdraw a discount' => [static fn (Order $order) => $order->withdrawManualDiscount('m')];
    }

    /**
     * @param callable(Order): void $change
     */
    #[DataProvider('cartOnly')]
    public function testOnlyACartTakesThem(callable $change): void
    {
        $order = $this->placed();

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 is placed: its lines and adjustments no longer change.');

        $change($order);
    }

    public function testManualDiscountsAreGrantedOnceByKeyAndWithdrawn(): void
    {
        $order = $this->cart();
        $line = ManualDiscount::amount('box', Money::of(100, Currency::of('CAD')), 'Damaged box', 'e', SellableReference::of('product', 'dice'));
        $whole = ManualDiscount::share('loyal', 500, 'Regular', 'e');

        $order->grantManualDiscount($line);
        $order->grantManualDiscount($whole);
        self::assertEquals([$line, $whole], $order->manualDiscounts());

        $order->withdrawManualDiscount('box');
        self::assertEquals([$whole], $order->manualDiscounts());

        $order->withdrawManualDiscount('unknown');
        self::assertEquals([$whole], $order->manualDiscounts());
    }

    public function testAKeyIsGrantedOnce(): void
    {
        $order = $this->cart();
        $order->grantManualDiscount(ManualDiscount::share('loyal', 500, 'Regular', 'e'));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 already has a manual discount "loyal".');

        $order->grantManualDiscount(ManualDiscount::share('loyal', 100, 'Again', 'e'));
    }

    public function testADiscountIsInTheOrdersCurrency(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->cart()->grantManualDiscount(ManualDiscount::amount('m', Money::of(1, Currency::of('USD')), 'R', 'e'));
    }

    public function testADiscountOnALineNeedsTheLine(): void
    {
        $this->expectException(LineNotInOrder::class);

        $this->cart()->grantManualDiscount(ManualDiscount::share('m', 100, 'R', 'e', SellableReference::of('product', 'pen')));
    }

    public function testAStatusBelongsToTheStateItIsAttachedTo(): void
    {
        $order = $this->placed();
        self::assertNull($order->status());

        $order->changeStatus('awaiting_pickup', OrderState::Placed);
        self::assertSame('awaiting_pickup', $order->status());

        $order->changeStatus(null, OrderState::Placed);
        self::assertNull($order->status());

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 is placed: the status "backorder" belongs to cart orders.');

        $order->changeStatus('backorder', OrderState::Cart);
    }

    public function testAStatusIsAPlainName(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"awaiting pickup" is not a status');

        $this->placed()->changeStatus('awaiting pickup', OrderState::Placed);
    }

    /**
     * @return iterable<string, array{callable(Order): void}>
     */
    public static function moves(): iterable
    {
        yield 'reopen' => [static fn (Order $order) => $order->reopen()];
        yield 'cancel' => [static fn (Order $order) => $order->cancel(new DateTimeImmutable())];
        yield 'expire' => [static fn (Order $order) => $order->expire(new DateTimeImmutable())];
        yield 'force' => [static fn (Order $order) => $order->force(OrderState::Quoted, new DateTimeImmutable())];
    }

    /**
     * @param callable(Order): void $move
     */
    #[DataProvider('moves')]
    public function testAMoveOfTheStateClearsTheStatus(callable $move): void
    {
        $order = $this->placed();
        $order->changeStatus('awaiting_pickup', OrderState::Placed);

        $move($order);

        self::assertNull($order->status());
    }

    public function testQuotingAndPlacingClearTheStatus(): void
    {
        $order = $this->cart();
        $order->changeStatus('draft', OrderState::Cart);
        $order->quote();
        self::assertNull($order->status());

        $order->changeStatus('sent', OrderState::Quoted);
        $order->place(new DateTimeImmutable());
        self::assertNull($order->status());
    }

    public function testEachConfirmedChangeCountsARevision(): void
    {
        $order = $this->placed();
        self::assertSame(1, $order->revision());
        self::assertSame(2, $order->recordRevision());
        self::assertSame(3, $order->recordRevision());
        self::assertSame(3, $order->revision());

        $order->reopen();

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 is cart: its draws are made once it is placed and paid.');

        $order->recordRevision();
    }

    public function testAnUnpaidPlacedOrderExpires(): void
    {
        $order = $this->placed();
        $at = new DateTimeImmutable('2026-11-12T10:00:00Z');
        self::assertNull($order->expiredAt());

        $order->expire($at);

        self::assertSame(OrderState::Cancelled, $order->state());
        self::assertSame($at, $order->expiredAt());
        self::assertSame($at, $order->cancelledAt());
    }

    public function testOnlyAPlacedOrderExpires(): void
    {
        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 is cart: it cannot become cancelled.');

        $this->cart()->expire(new DateTimeImmutable());
    }

    public function testAForcedMoveSetsTheMomentsOfItsState(): void
    {
        $placedAt = new DateTimeImmutable('2026-11-01T10:00:00Z');
        $cancelledAt = new DateTimeImmutable('2026-11-02T10:00:00Z');
        $again = new DateTimeImmutable('2026-11-03T10:00:00Z');
        $order = $this->placed($placedAt);
        $order->expire($cancelledAt);

        $order->force(OrderState::Placed, $again);
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame($again, $order->placedAt());
        self::assertNull($order->cancelledAt());
        self::assertNull($order->expiredAt());

        $order->force(OrderState::Cancelled, $cancelledAt);
        self::assertSame($cancelledAt, $order->cancelledAt());
        self::assertSame($again, $order->placedAt());

        $order->force(OrderState::Cart, $again);
        self::assertSame(OrderState::Cart, $order->state());
        self::assertNull($order->placedAt());
        self::assertNull($order->cancelledAt());
    }

    public function testAMoveToTheSameStateIsNoForcedMove(): void
    {
        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 is placed: it cannot become placed.');

        $this->placed()->force(OrderState::Placed, new DateTimeImmutable());
    }

    public function testAnEmptyOrderIsNeverForcedToPlaced(): void
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->cancel(new DateTimeImmutable());

        $this->expectException(OrderLocked::class);
        $this->expectExceptionMessageIsOrContains('The order 2026-0001 has no line: an empty cart cannot be placed.');

        $order->force(OrderState::Placed, new DateTimeImmutable());
    }

    private function cart(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $price = Money::of(1_000, Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $price, 'goods'), 1, $price);

        return $order;
    }

    private function placed(?DateTimeImmutable $at = null): Order
    {
        $order = $this->cart();
        $order->place($at ?? new DateTimeImmutable());

        return $order;
    }
}
