<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Payment::class)]
#[CoversClass(PaymentRefused::class)]
final class PaymentTest extends TestCase
{
    private int $sequence = 0;

    public function testANewPaymentHoldsItsOrderMethodAndAmount(): void
    {
        $at = new DateTimeImmutable('2026-11-03T15:00:00Z');
        $payment = Payment::create($this->order(), 'card', $this->cad(1_000), $at);

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $payment->id);
        self::assertNotSame($payment->id, Payment::create($this->order(), 'card', $this->cad(1_000), $at)->id);
        self::assertSame('1', $payment->orderNumber);
        self::assertSame('card', $payment->method);
        self::assertSame($at, $payment->createdAt);
        self::assertSame(1_000, $payment->amount->minorUnits);
        self::assertSame('CAD', $payment->amount->currency->code);
        self::assertTrue($payment->refunded->isZero());
        self::assertSame('CAD', $payment->refunded->currency->code);
        self::assertSame(PaymentState::New, $payment->state());
        self::assertNull($payment->externalReference());
        self::assertSame(1, $payment->version());
        self::assertTrue($payment->counts());
        self::assertFalse($payment->wasCaptured());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidMethods(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Card'];
        yield 'starting with a digit' => ['1card'];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'trailing newline' => ["card\n"];
    }

    #[DataProvider('invalidMethods')]
    public function testAnInvalidMethodIsRefused(string $method): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a payment method: up to 32 lowercase letters, digits or "_", starting with a letter.', $method));

        Payment::create($this->order(), $method, $this->cad(1), new DateTimeImmutable());
    }

    public function testTheBoundsOfAMethodAreAccepted(): void
    {
        self::assertSame('a', Payment::create($this->order(), 'a', $this->cad(1), new DateTimeImmutable())->method);
        self::assertSame('a'.str_repeat('1', 31), Payment::create($this->order(), 'a'.str_repeat('1', 31), $this->cad(1), new DateTimeImmutable())->method);
    }

    public function testAnAmountInAnotherCurrencyThanTheOrderIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Payment::create($this->order(), 'card', Money::of(1, Currency::of('USD')), new DateTimeImmutable());
    }

    public function testHandingOverKeepsTheProcessorsReference(): void
    {
        $payment = $this->payment();
        $payment->handOver(PaymentStart::of('pi_123'));

        self::assertSame(PaymentState::Processing, $payment->state());
        self::assertSame('pi_123', $payment->externalReference());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s was already handed to its processor.', $payment->id));

        $payment->handOver(PaymentStart::of('pi_456'));
    }

    /**
     * @return iterable<string, array{list<PaymentEvent>, PaymentState}>
     */
    public static function paths(): iterable
    {
        yield 'processing' => [[PaymentEvent::Processing], PaymentState::Processing];
        yield 'authorized' => [[PaymentEvent::Authorized], PaymentState::Authorized];
        yield 'authorized while processing' => [[PaymentEvent::Processing, PaymentEvent::Authorized], PaymentState::Authorized];
        yield 'captured at once' => [[PaymentEvent::Captured], PaymentState::Completed];
        yield 'captured while processing' => [[PaymentEvent::Processing, PaymentEvent::Captured], PaymentState::Completed];
        yield 'captured once authorized' => [[PaymentEvent::Authorized, PaymentEvent::Captured], PaymentState::Completed];
        yield 'failed' => [[PaymentEvent::Failed], PaymentState::Failed];
        yield 'failed while processing' => [[PaymentEvent::Processing, PaymentEvent::Failed], PaymentState::Failed];
        yield 'failed once authorized' => [[PaymentEvent::Authorized, PaymentEvent::Failed], PaymentState::Failed];
        yield 'cancelled' => [[PaymentEvent::Cancelled], PaymentState::Cancelled];
        yield 'cancelled while processing' => [[PaymentEvent::Processing, PaymentEvent::Cancelled], PaymentState::Cancelled];
        yield 'cancelled once authorized' => [[PaymentEvent::Authorized, PaymentEvent::Cancelled], PaymentState::Cancelled];
        yield 'disputed' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened], PaymentState::Disputed];
        yield 'dispute won' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened, PaymentEvent::DisputeWon], PaymentState::Completed];
        yield 'dispute lost' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened, PaymentEvent::DisputeLost], PaymentState::ChargedBack];
    }

    /**
     * @param list<PaymentEvent> $events
     */
    #[DataProvider('paths')]
    public function testNotificationsMoveThePayment(array $events, PaymentState $expected): void
    {
        $payment = $this->payment();

        foreach ($events as $event) {
            self::assertTrue($payment->apply($this->notification($event)));
        }

        self::assertSame($expected, $payment->state());
    }

    /**
     * @return iterable<string, array{list<PaymentEvent>, PaymentEvent, PaymentState}>
     */
    public static function refusedEvents(): iterable
    {
        yield 'processing once authorized' => [[PaymentEvent::Authorized], PaymentEvent::Processing, PaymentState::Authorized];
        yield 'authorized once captured' => [[PaymentEvent::Captured], PaymentEvent::Authorized, PaymentState::Completed];
        yield 'captured twice' => [[PaymentEvent::Captured], PaymentEvent::Captured, PaymentState::Completed];
        yield 'captured once failed' => [[PaymentEvent::Failed], PaymentEvent::Captured, PaymentState::Failed];
        yield 'failed once captured' => [[PaymentEvent::Captured], PaymentEvent::Failed, PaymentState::Completed];
        yield 'cancelled once captured' => [[PaymentEvent::Captured], PaymentEvent::Cancelled, PaymentState::Completed];
        yield 'disputed before capture' => [[PaymentEvent::Authorized], PaymentEvent::DisputeOpened, PaymentState::Authorized];
        yield 'dispute won without a dispute' => [[PaymentEvent::Captured], PaymentEvent::DisputeWon, PaymentState::Completed];
        yield 'dispute lost without a dispute' => [[PaymentEvent::Captured], PaymentEvent::DisputeLost, PaymentState::Completed];
        yield 'refunded before capture' => [[PaymentEvent::Authorized], PaymentEvent::Refunded, PaymentState::Authorized];
        yield 'refunded while disputed' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened], PaymentEvent::Refunded, PaymentState::Disputed];
    }

    /**
     * @param list<PaymentEvent> $before
     */
    #[DataProvider('refusedEvents')]
    public function testANotificationThatDoesNotApplyIsRefusedAndLeavesThePayment(array $before, PaymentEvent $event, PaymentState $state): void
    {
        $payment = $this->payment();

        foreach ($before as $earlier) {
            $payment->apply($this->notification($earlier));
        }

        try {
            $payment->apply(PaymentEvent::Refunded === $event ? $this->refund(100) : $this->notification($event, 'refused'));
            self::fail('The notification was applied.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame(\sprintf('The payment %s is %s: a "%s" notification does not apply to it.', $payment->id, $state->value, $event->value), $paymentRefused->getMessage());
        }

        self::assertSame($state, $payment->state());
        self::assertTrue($payment->refunded->isZero());
    }

    /**
     * A refused notification is not recorded: the same id applies once the payment allows it.
     */
    public function testARefusedNotificationIsNotRecorded(): void
    {
        $payment = $this->payment();

        try {
            $payment->apply(PaymentNotification::of('evt_x', 'pi_1', PaymentEvent::DisputeOpened));
        } catch (PaymentRefused) {
        }

        self::assertTrue($payment->apply(PaymentNotification::of('evt_x', 'pi_1', PaymentEvent::Captured)));
    }

    public function testANotificationAppliesOnce(): void
    {
        $payment = $this->payment();
        $captured = PaymentNotification::of('evt_1', 'pi_1', PaymentEvent::Captured);

        self::assertFalse($payment->hasApplied('evt_1'));
        self::assertTrue($payment->apply($captured));
        self::assertTrue($payment->hasApplied('evt_1'));
        self::assertFalse($payment->hasApplied('evt_2'));
        self::assertFalse($payment->apply($captured));
        self::assertFalse($payment->apply(PaymentNotification::of('evt_1', 'pi_1', PaymentEvent::Failed)));
        self::assertSame(PaymentState::Completed, $payment->state());
    }

    /**
     * Partial refunds keep the payment completed; the last one makes it refunded.
     */
    public function testRefundsAddUpToTheAmount(): void
    {
        $payment = $this->payment();
        $payment->apply($this->notification(PaymentEvent::Captured));

        self::assertTrue($payment->apply($this->refund(300)));
        self::assertSame(PaymentState::Completed, $payment->state());
        self::assertSame(300, $payment->refunded->minorUnits);
        self::assertSame(700, $payment->refundable()->minorUnits);

        self::assertTrue($payment->apply($this->refund(700)));
        self::assertSame(PaymentState::Refunded, $payment->state());
        self::assertSame(1_000, $payment->refunded->minorUnits);
        self::assertTrue($payment->refundable()->isZero());
        self::assertTrue($payment->wasCaptured());
        self::assertFalse($payment->counts());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function refusedRefunds(): iterable
    {
        yield 'more than is left' => [1_001, 'A refund of 10.01 CAD is refused: it must be above zero and at most 10.00 CAD.'];
        yield 'zero' => [0, 'A refund of 0.00 CAD is refused: it must be above zero and at most 10.00 CAD.'];
        yield 'negative' => [-1, 'A refund of -0.01 CAD is refused: it must be above zero and at most 10.00 CAD.'];
    }

    #[DataProvider('refusedRefunds')]
    public function testARefundBeyondWhatIsLeftIsRefused(int $minorUnits, string $message): void
    {
        $payment = $this->payment();
        $payment->apply($this->notification(PaymentEvent::Captured));

        try {
            $payment->apply($this->refund($minorUnits));
            self::fail('The refund was applied.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame($message, $paymentRefused->getMessage());
        }

        self::assertTrue($payment->refunded->isZero());
        self::assertSame(PaymentState::Completed, $payment->state());
    }

    /**
     * @return iterable<string, array{list<PaymentEvent>, bool, bool}>
     */
    public static function holdings(): iterable
    {
        yield 'new' => [[], true, false];
        yield 'processing' => [[PaymentEvent::Processing], true, false];
        yield 'authorized' => [[PaymentEvent::Authorized], true, false];
        yield 'completed' => [[PaymentEvent::Captured], true, true];
        yield 'disputed' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened], true, true];
        yield 'charged back' => [[PaymentEvent::Captured, PaymentEvent::DisputeOpened, PaymentEvent::DisputeLost], false, true];
        yield 'failed' => [[PaymentEvent::Failed], false, false];
        yield 'cancelled' => [[PaymentEvent::Cancelled], false, false];
    }

    /**
     * @param list<PaymentEvent> $events
     */
    #[DataProvider('holdings')]
    public function testWhatAPaymentHoldsFollowsItsState(array $events, bool $counts, bool $captured): void
    {
        $payment = $this->payment();

        foreach ($events as $event) {
            $payment->apply($this->notification($event));
        }

        self::assertSame($counts, $payment->counts());
        self::assertSame($captured, $payment->wasCaptured());
    }

    public function testANewOrProcessingPaymentIsAbandonedAndFreesItsShare(): void
    {
        $new = $this->payment();
        $new->abandon();

        self::assertSame(PaymentState::Abandoned, $new->state());
        self::assertFalse($new->counts());
        self::assertFalse($new->wasCaptured());

        $processing = $this->payment();
        $processing->handOver(PaymentStart::of('pi_1', 'https://pay.example/1'));
        $processing->abandon();

        self::assertSame(PaymentState::Abandoned, $processing->state());
    }

    /**
     * @return iterable<string, array{PaymentEvent}>
     */
    public static function settledEvents(): iterable
    {
        yield 'authorized' => [PaymentEvent::Authorized];
        yield 'captured' => [PaymentEvent::Captured];
        yield 'failed' => [PaymentEvent::Failed];
        yield 'cancelled' => [PaymentEvent::Cancelled];
    }

    #[DataProvider('settledEvents')]
    public function testOnlyASettledPaymentIsNotAbandoned(PaymentEvent $event): void
    {
        $payment = $this->payment();
        $payment->apply($this->notification($event));

        $state = $payment->state();

        $this->expectExceptionObject(PaymentRefused::notAbandonable($payment->id, $state));
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s is %s: only a new or processing payment is abandoned.', $payment->id, $state->value));

        $payment->abandon();
    }

    /**
     * A processor that captures an abandoned payment after all: the money is recorded, never lost.
     *
     * @return iterable<string, array{PaymentEvent, PaymentState, bool}>
     */
    public static function lateNotifications(): iterable
    {
        yield 'captured' => [PaymentEvent::Captured, PaymentState::Completed, true];
        yield 'authorized' => [PaymentEvent::Authorized, PaymentState::Authorized, true];
        yield 'failed' => [PaymentEvent::Failed, PaymentState::Failed, false];
        yield 'cancelled' => [PaymentEvent::Cancelled, PaymentState::Cancelled, false];
    }

    #[DataProvider('lateNotifications')]
    public function testALateNotificationAppliesToAnAbandonedPayment(PaymentEvent $event, PaymentState $state, bool $counts): void
    {
        $payment = $this->payment();
        $payment->abandon();

        self::assertTrue($payment->apply($this->notification($event)));
        self::assertSame($state, $payment->state());
        self::assertSame($counts, $payment->counts());
    }

    public function testAnAbandonedPaymentIsNotProcessingAgain(): void
    {
        $payment = $this->payment();
        $payment->abandon();

        $this->expectException(PaymentRefused::class);

        $payment->apply($this->notification(PaymentEvent::Processing));
    }

    /**
     * A refund notification in another currency is refused like any refund that cannot apply, never a crash.
     */
    public function testARefundInAnotherCurrencyIsRefused(): void
    {
        $payment = $this->payment();
        $payment->apply($this->notification(PaymentEvent::Captured));

        try {
            $payment->apply(PaymentNotification::of('evt_usd', 'pi_1', PaymentEvent::Refunded, Money::of(100, Currency::of('USD'))));
            self::fail('The refund was applied.');
        } catch (PaymentRefused $paymentRefused) {
            self::assertSame(\sprintf('The payment %s is in CAD: a notification in USD does not apply to it.', $payment->id), $paymentRefused->getMessage());
        }

        self::assertTrue($payment->refunded->isZero());
        self::assertSame(PaymentState::Completed, $payment->state());
    }

    public function testARefusalIsADomainError(): void
    {
        self::assertInstanceOf(DomainError::class, PaymentRefused::unknownMethod('card'));
        self::assertSame('No payment gateway supports the method "card".', PaymentRefused::unknownMethod('card')->getMessage());
        self::assertSame('The order 1 is not placed: only a placed order is paid.', PaymentRefused::orderNotPlaced('1')->getMessage());
        self::assertSame('The payment p is failed: only a completed payment is refunded.', PaymentRefused::notRefundable('p', PaymentState::Failed)->getMessage());
    }

    private function payment(): Payment
    {
        return Payment::create($this->order(), 'card', $this->cad(1_000), new DateTimeImmutable());
    }

    private function notification(PaymentEvent $event, string $prefix = 'evt'): PaymentNotification
    {
        return PaymentNotification::of($prefix.'_'.++$this->sequence, 'pi_1', $event);
    }

    private function refund(int $minorUnits): PaymentNotification
    {
        return PaymentNotification::of('evt_'.++$this->sequence, 'pi_1', PaymentEvent::Refunded, $this->cad($minorUnits));
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
