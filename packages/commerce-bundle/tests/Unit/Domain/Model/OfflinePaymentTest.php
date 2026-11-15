<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Payment::class)]
#[CoversClass(PaymentRefused::class)]
final class OfflinePaymentTest extends TestCase
{
    public function testAnOfflinePaymentWaitsUntilItsMoneyComesIn(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());

        self::assertTrue($payment->isOffline());
        self::assertSame(PaymentState::Processing, $payment->state());
        self::assertSame('offline:'.$payment->id, $payment->externalReference());
        self::assertTrue($payment->counts());

        $payment->confirmReceived();

        self::assertSame(PaymentState::Completed, $payment->state());
    }

    public function testAnOnlinePaymentIsNeverConfirmedByHand(): void
    {
        $payment = Payment::create($this->order(), 'card', $this->cad(1_000), new DateTimeImmutable());
        self::assertFalse($payment->isOffline());
        $payment->handOver(PaymentStart::of('ref-1'));

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s is processing: only an offline payment awaiting it is confirmed by hand.', $payment->id));

        $payment->confirmReceived();
    }

    public function testAnOfflinePaymentIsConfirmedOnce(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());
        $payment->confirmReceived();

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s is completed: only an offline payment awaiting it is confirmed by hand.', $payment->id));

        $payment->confirmReceived();
    }

    public function testAnOfflinePaymentWhoseMoneyNeverCameIsCancelled(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());

        $payment->cancelOffline();

        self::assertSame(PaymentState::Cancelled, $payment->state());
        self::assertFalse($payment->counts());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('is cancelled: only an offline payment awaiting it is cancelled by hand.');

        $payment->cancelOffline();
    }

    public function testAnOfflineRefundIsRecordedAtOnce(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());
        $payment->confirmReceived();

        $payment->recordOfflineRefund($this->cad(400));
        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertSame(400, $payment->refunded->minorUnits);

        $payment->recordOfflineRefund($this->cad(600));
        self::assertSame(PaymentState::Refunded, $payment->state());
        self::assertSame(1_000, $payment->refunded->minorUnits);
    }

    public function testAnOfflineRefundIsBoundedAndInTheSameCurrency(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());
        $payment->confirmReceived();

        foreach ([[$this->cad(1_001), 'A refund of 10.01 CAD is refused: it must be above zero and at most 10.00 CAD.'], [$this->cad(0), 'A refund of 0.00 CAD is refused'], [Money::of(1, Currency::of('USD')), 'USD']] as [$amount, $message]) {
            try {
                $payment->recordOfflineRefund($amount);
                self::fail('Refused: '.$message);
            } catch (PaymentRefused $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }

        self::assertTrue($payment->refunded->isZero());
    }

    public function testOnlyACompletedOfflinePaymentIsRefundedByHand(): void
    {
        $payment = Payment::declareOffline($this->order(), 'cheque', $this->cad(1_000), new DateTimeImmutable());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains('is processing: only an offline payment awaiting it is refunded by hand.');

        $payment->recordOfflineRefund($this->cad(1));
    }

    public function testTheMethodRefusalNamesTheConfiguration(): void
    {
        self::assertSame('"card" is not a method of phil_commerce.offline_payments.', PaymentRefused::notOfflineMethod('card')->getMessage());
    }

    private function order(): Order
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable());

        return $order;
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }
}
