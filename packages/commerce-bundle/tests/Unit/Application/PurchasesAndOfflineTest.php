<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\NotificationOutcome;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\CommerceBundle\Tests\Unit\Support\RecordingDispatcher;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Offline payments, and the purchase announced once an order is paid in full.
 */
#[CoversClass(Payments::class)]
#[CoversClass(OrderPurchased::class)]
final class PurchasesAndOfflineTest extends TestCase
{
    private InMemoryPayments $repository;

    private InMemoryOrders $orders;

    private RecordingDispatcher $dispatcher;

    private FakeGateway $card;

    private Payments $payments;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->repository = new InMemoryPayments();
        $this->orders = new InMemoryOrders();
        $this->dispatcher = new RecordingDispatcher();
        $this->card = new FakeGateway('card');
        $this->clock = new MockClock('2026-11-11 10:00:00');
        $this->payments = new Payments([$this->card], $this->repository, $this->orders, $this->dispatcher, ['cheque', 'bank_transfer'], $this->clock);
    }

    public function testAnOrderPaidInFullIsAnnouncedOnce(): void
    {
        $order = $this->order();
        $this->payments->start($order, 'card', new DateTimeImmutable(), $this->cad(400));
        $this->payments->start($order, 'card', new DateTimeImmutable());

        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);
        self::assertSame([], $this->dispatcher->events);

        self::assertSame(NotificationOutcome::Applied, $this->payments->notify('card', 'evt_2|ref-2|captured', ['x-signature' => ['ok']])[0]);
        self::assertEquals([new OrderPurchased('2026-0001', 'store', 'customer-1', $this->cad(1_000), $this->clock->now())], $this->dispatcher->events);

        $this->payments->notify('card', 'evt_2|ref-2|captured', ['x-signature' => ['ok']]);
        $this->payments->notify('card', 'evt_3|ref-2|refunded|100', ['x-signature' => ['ok']]);
        self::assertCount(1, $this->dispatcher->heard());
    }

    public function testARefusedNotificationAnnouncesNothing(): void
    {
        $order = $this->order();
        $this->payments->start($order, 'card', new DateTimeImmutable());
        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        $this->dispatcher->events = [];

        self::assertSame(NotificationOutcome::Refused, $this->payments->notify('card', 'evt_2|ref-1|authorized', ['x-signature' => ['ok']])[0]);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testWithoutTheOrdersNothingIsAnnounced(): void
    {
        $payments = new Payments([$this->card], $this->repository, dispatcher: $this->dispatcher);
        $payments->start($this->order(), 'card', new DateTimeImmutable());

        $payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        self::assertSame([], $this->dispatcher->events);
    }

    public function testAnOfflinePaymentIsDeclaredThenConfirmed(): void
    {
        $order = $this->order();
        $at = new DateTimeImmutable('2026-11-11T09:00:00Z');

        $payment = $this->payments->declareOffline($order, 'cheque', $at);

        self::assertSame([$payment], $this->repository->payments);
        self::assertSame(1_000, $payment->amount->minorUnits);
        self::assertSame($at, $payment->createdAt);
        self::assertSame(1, $order->paymentsStarted());
        self::assertTrue($this->payments->summary($order)->payable()->isZero());
        self::assertSame(['cheque', 'bank_transfer'], $this->payments->offlineMethods());

        $confirmed = new DateTimeImmutable('2026-11-12T09:00:00Z');
        $this->payments->confirmOffline($order, $payment, $confirmed);

        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertEquals([new OrderPurchased('2026-0001', 'store', 'customer-1', $this->cad(1_000), $confirmed)], $this->dispatcher->events);
    }

    public function testAPartOfflineAndAPartOnline(): void
    {
        $order = $this->order();
        $payment = $this->payments->declareOffline($order, 'bank_transfer', new DateTimeImmutable(), $this->cad(300));

        self::assertSame(300, $payment->amount->minorUnits);
        self::assertSame(700, $this->payments->summary($order)->payable()->minorUnits);

        $this->payments->confirmOffline($order, $payment, new DateTimeImmutable());
        self::assertSame([], $this->dispatcher->events);
    }

    public function testAnOfflinePaymentIsNeverAbandonedAsStale(): void
    {
        $order = $this->order();
        $payment = $this->payments->declareOffline($order, 'cheque', new DateTimeImmutable('2026-11-01T00:00:00Z'));

        self::assertSame([], $this->payments->abandonStale($order, new DateTimeImmutable('2026-11-11T00:00:00Z')));

        $this->payments->cancelOffline($payment);
        self::assertSame(PaymentState::Cancelled, $payment->state());
        self::assertSame(1_000, $this->payments->summary($order)->payable()->minorUnits);
    }

    public function testAnOfflineRefundIsRecordedWithoutAGateway(): void
    {
        $order = $this->order();
        $payment = $this->payments->declareOffline($order, 'cheque', new DateTimeImmutable());
        $this->payments->confirmOffline($order, $payment, new DateTimeImmutable());

        $this->payments->refund($payment, $this->cad(250));

        self::assertSame(250, $payment->refunded->minorUnits);
        self::assertSame([], $this->card->refunds);
    }

    public function testOnlyADeclaredMethodIsPaidOffline(): void
    {
        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('"card" is not a method of phil_commerce.offline_payments.');

        $this->payments->declareOffline($this->order(), 'card', new DateTimeImmutable());
    }

    public function testOnlyAPlacedOrderIsPaidOffline(): void
    {
        $order = Order::open('2026-0002', Currency::of('CAD'));

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('2026-0002');

        $this->payments->declareOffline($order, 'cheque', new DateTimeImmutable());
    }

    public function testAnOfflinePaymentTakesNoMoreThanIsLeft(): void
    {
        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('A payment of 10.01 CAD is refused: it must be above zero and at most 10.00 CAD.');

        $this->payments->declareOffline($this->order(), 'cheque', new DateTimeImmutable(), $this->cad(1_001));
    }

    public function testAnOfflinePaymentIsAboveZero(): void
    {
        $this->expectException(PaymentRefused::class);

        $this->payments->declareOffline($this->order(), 'cheque', new DateTimeImmutable(), $this->cad(0));
    }

    public function testAPaymentIsConfirmedForItsOwnOrder(): void
    {
        $payment = $this->payments->declareOffline($this->order(), 'cheque', new DateTimeImmutable());
        $other = Order::open('2026-0002', Currency::of('CAD'));

        $this->expectException(PaymentRefused::class);

        $this->payments->confirmOffline($other, $payment, new DateTimeImmutable());
    }

    private function order(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->changeChannel('store');
        $order->assignCustomer('customer-1');
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable());

        $this->orders->add($order);

        return $order;
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }

    public function testAPurchaseIsAnnouncedAtTheSystemClockWhenThereIsNoOther(): void
    {
        $payments = new Payments([$this->card], $this->repository, $this->orders, $this->dispatcher);
        $payments->start($this->order(), 'card', new DateTimeImmutable());

        $before = new DateTimeImmutable();

        $payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(OrderPurchased::class, $event);
        self::assertGreaterThanOrEqual($before->getTimestamp(), $event->occurredAt()->getTimestamp());
    }

    public function testAnOrderPaidInFullIsPaidWhetherOrNotAnyoneListens(): void
    {
        $payments = new Payments([$this->card], $this->repository, $this->orders, clock: $this->clock);
        $order = $this->order();
        $payments->start($order, 'card', new DateTimeImmutable());

        self::assertSame(NotificationOutcome::Applied, $payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']])[0]);
    }
}
