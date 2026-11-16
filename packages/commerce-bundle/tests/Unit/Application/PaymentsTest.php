<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\NotificationOutcome;
use Phil\CommerceBundle\Application\Payments;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Tests\Unit\Support\FakeGateway;
use Phil\CommerceBundle\Tests\Unit\Support\InMemoryPayments;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Payments::class)]
final class PaymentsTest extends TestCase
{
    private InMemoryPayments $repository;

    private FakeGateway $card;

    private Payments $payments;

    protected function setUp(): void
    {
        $this->repository = new InMemoryPayments();
        $this->card = new FakeGateway('card');
        $this->payments = new Payments([new FakeGateway('interac'), $this->card], $this->repository);
    }

    public function testAPaymentOfWhatIsLeftIsHandedToItsGateway(): void
    {
        $at = new DateTimeImmutable('2026-11-03T15:00:00Z');
        [$payment, $start] = $this->payments->start($this->order(), 'card', $at);

        self::assertSame([$payment], $this->repository->payments);
        self::assertSame(1_000, $payment->amount->minorUnits);
        self::assertSame('card', $payment->method);
        self::assertSame($at, $payment->createdAt);
        self::assertSame(PaymentState::Processing, $payment->state());
        self::assertSame('ref-1', $payment->externalReference());
        self::assertSame('ref-1', $start->externalReference);
        self::assertSame('https://pay.example/'.$payment->id, $start->redirectUrl);
    }

    /**
     * A split payment: each part reserves its share, the last may take all that is left.
     */
    public function testASplitPaymentTakesPartsOfWhatIsLeft(): void
    {
        $order = $this->order();

        [$first] = $this->payments->start($order, 'card', new DateTimeImmutable(), $this->cad(400));
        [$second] = $this->payments->start($order, 'card', new DateTimeImmutable());

        self::assertSame(400, $first->amount->minorUnits);
        self::assertSame(600, $second->amount->minorUnits);
        self::assertTrue($this->payments->summary($order)->payable()->isZero());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function refusedAmounts(): iterable
    {
        yield 'more than is left' => [1_001, 'A payment of 10.01 CAD is refused: it must be above zero and at most 10.00 CAD.'];
        yield 'zero' => [0, 'A payment of 0.00 CAD is refused: it must be above zero and at most 10.00 CAD.'];
        yield 'negative' => [-5, 'A payment of -0.05 CAD is refused: it must be above zero and at most 10.00 CAD.'];
    }

    #[DataProvider('refusedAmounts')]
    public function testAnAmountBeyondWhatIsLeftIsRefused(int $minorUnits, string $message): void
    {
        try {
            $this->payments->start($this->order(), 'card', new DateTimeImmutable(), $this->cad($minorUnits));
            self::fail('The payment was started.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame($message, $paymentRefused->getMessage());
        }

        self::assertSame([], $this->repository->payments);
    }

    public function testNothingIsLeftOnceEverythingIsReserved(): void
    {
        $order = $this->order();
        $this->payments->start($order, 'card', new DateTimeImmutable());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('A payment of 0.00 CAD is refused: it must be above zero and at most 0.00 CAD.');

        $this->payments->start($order, 'card', new DateTimeImmutable());
    }

    /**
     * Each start moves the order's version on, so that a second start over a stale copy of the order cannot be
     * committed beside the first.
     */
    public function testEachStartIsCountedOnTheOrder(): void
    {
        $order = $this->order();

        $this->payments->start($order, 'card', new DateTimeImmutable(), $this->cad(400));
        $this->payments->start($order, 'card', new DateTimeImmutable());

        self::assertSame(2, $order->paymentsStarted());
    }

    public function testARefusedStartIsNotCounted(): void
    {
        $order = $this->order();

        try {
            $this->payments->start($order, 'card', new DateTimeImmutable(), $this->cad(1_001));
        } catch (PaymentRefused) {
        }

        self::assertSame(0, $order->paymentsStarted());
    }

    /**
     * A checkout the buyer left holds the whole balance until it is abandoned; then a new attempt may take it.
     */
    public function testAnAbandonedPaymentFreesTheBalance(): void
    {
        $order = $this->order();
        [$left] = $this->payments->start($order, 'card', new DateTimeImmutable());

        $this->payments->abandon($left);
        [$retry] = $this->payments->start($order, 'card', new DateTimeImmutable());

        self::assertSame(PaymentState::Abandoned, $left->state());
        self::assertSame(1_000, $retry->amount->minorUnits);
    }

    public function testStalePaymentsAreAbandonedByTheirAge(): void
    {
        $order = $this->order();
        [$old] = $this->payments->start($order, 'card', new DateTimeImmutable('2026-11-03T10:00:00Z'), $this->cad(200));
        [$captured] = $this->payments->start($order, 'card', new DateTimeImmutable('2026-11-03T10:00:00Z'), $this->cad(300));
        $this->payments->notify('card', 'evt_1|ref-2|captured', ['x-signature' => ['ok']]);
        [$older] = $this->payments->start($order, 'card', new DateTimeImmutable('2026-11-03T09:00:00Z'), $this->cad(100));
        [$recent] = $this->payments->start($order, 'card', new DateTimeImmutable('2026-11-03T11:30:00Z'), $this->cad(300));
        [$exact] = $this->payments->start($order, 'card', new DateTimeImmutable('2026-11-03T11:00:00Z'), $this->cad(100));

        self::assertSame([$old, $older], $this->payments->abandonStale($order, new DateTimeImmutable('2026-11-03T11:00:00Z')));
        self::assertSame(PaymentState::Abandoned, $old->state());
        self::assertSame(PaymentState::Abandoned, $older->state());
        self::assertSame(PaymentState::Completed, $captured->state());
        self::assertSame(PaymentState::Processing, $recent->state());
        self::assertSame(PaymentState::Processing, $exact->state());
        self::assertSame(300, $this->payments->summary($order)->payable()->minorUnits);
    }

    public function testAnAmountInAnotherCurrencyIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        $this->payments->start($this->order(), 'card', new DateTimeImmutable(), Money::of(100, Currency::of('USD')));
    }

    public function testACartIsNotPaid(): void
    {
        $order = Order::open('9', Currency::of('CAD'));

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('The order 9 is not placed: only a placed order is paid.');

        $this->payments->start($order, 'card', new DateTimeImmutable());
    }

    public function testAMethodNoGatewaySupportsIsRefused(): void
    {
        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('No payment gateway supports the method "paypal".');

        $this->payments->start($this->order(), 'paypal', new DateTimeImmutable());
    }

    public function testANotificationMovesThePaymentOnce(): void
    {
        $order = $this->order();
        [$payment] = $this->payments->start($order, 'card', new DateTimeImmutable());

        self::assertSame([NotificationOutcome::Applied, $payment], $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]));
        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertSame([NotificationOutcome::AlreadyApplied, $payment], $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]));
        self::assertSame([NotificationOutcome::Refused, $payment], $this->payments->notify('card', 'evt_2|ref-1|authorized', ['x-signature' => ['ok']]));
        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertSame(OrderPaymentState::Paid, $this->payments->summary($order)->state());
    }

    public function testANotificationForAnUnknownPaymentIsSaidSo(): void
    {
        $this->payments->start($this->order(), 'card', new DateTimeImmutable());

        self::assertSame([NotificationOutcome::UnknownPayment, null], $this->payments->notify('card', 'evt_1|ref-9|captured', ['x-signature' => ['ok']]));
        self::assertSame([NotificationOutcome::UnknownPayment, null], $this->payments->notify('interac', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]));
    }

    public function testANotificationTheGatewayCannotTrustIsRefused(): void
    {
        $this->expectException(InvalidNotification::class);

        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['forged']]);
    }

    public function testARefundIsAskedOfTheGatewayAndRecordedOnlyWhenConfirmed(): void
    {
        [$payment] = $this->payments->start($this->order(), 'card', new DateTimeImmutable());
        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        $this->payments->refund($payment, $this->cad(250), 'refund-1');

        self::assertSame([[$payment->id, 250]], $this->card->refunds);
        self::assertTrue($payment->refunded->isZero());

        $this->payments->notify('card', 'evt_2|ref-1|refunded|250', ['x-signature' => ['ok']]);

        self::assertSame(250, $payment->refunded->minorUnits);
    }

    public function testOnlyACompletedPaymentIsRefunded(): void
    {
        [$payment] = $this->payments->start($this->order(), 'card', new DateTimeImmutable());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s is processing: only a completed payment is refunded.', $payment->id));

        $this->payments->refund($payment, $this->cad(1), 'refund-1');
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function refusedRefunds(): iterable
    {
        yield 'more than is left' => [1_001, 'A refund of 10.01 CAD is refused: it must be above zero and at most 10.00 CAD.'];
        yield 'zero' => [0, 'A refund of 0.00 CAD is refused: it must be above zero and at most 10.00 CAD.'];
    }

    #[DataProvider('refusedRefunds')]
    public function testARefundBeyondWhatIsLeftIsNotAsked(int $minorUnits, string $message): void
    {
        [$payment] = $this->payments->start($this->order(), 'card', new DateTimeImmutable());
        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        try {
            $this->payments->refund($payment, $this->cad($minorUnits), 'refund-1');
            self::fail('The refund was asked.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame($message, $paymentRefused->getMessage());
        }

        self::assertSame([], $this->card->refunds);
    }

    public function testARefundInAnotherCurrencyIsNotAsked(): void
    {
        [$payment] = $this->payments->start($this->order(), 'card', new DateTimeImmutable());
        $this->payments->notify('card', 'evt_1|ref-1|captured', ['x-signature' => ['ok']]);

        try {
            $this->payments->refund($payment, Money::of(1, Currency::of('USD')), 'refund-1');
            self::fail('The refund was asked.');
        } catch (CurrencyMismatch) {
        }

        self::assertSame([], $this->card->refunds);
    }

    private function order(): Order
    {
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable());

        return $order;
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
