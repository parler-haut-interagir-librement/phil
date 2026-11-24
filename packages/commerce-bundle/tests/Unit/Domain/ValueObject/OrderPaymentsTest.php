<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\OrderPayments;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderPayments::class)]
#[CoversClass(PaymentNotification::class)]
#[CoversClass(PaymentStart::class)]
final class OrderPaymentsTest extends TestCase
{
    private int $sequence = 0;

    public function testAnOrderWithoutPaymentIsUnpaidAndPayableInFull(): void
    {
        $summary = OrderPayments::of($this->order(), []);

        self::assertSame(1_000, $summary->total->minorUnits);
        self::assertSame([], $summary->payments);
        self::assertTrue($summary->captured()->isZero());
        self::assertTrue($summary->returned()->isZero());
        self::assertTrue($summary->net()->isZero());
        self::assertSame(1_000, $summary->payable()->minorUnits);
        self::assertSame(OrderPaymentState::Unpaid, $summary->state());
        self::assertSame('CAD', $summary->captured()->currency->code);
        self::assertSame('CAD', $summary->returned()->currency->code);
    }

    /**
     * A split payment: one part captured, one still with its processor, one failed.
     */
    public function testASplitPaymentReservesWhatIsInFlight(): void
    {
        $order = $this->order();
        $captured = $this->payment($order, 400, PaymentEvent::Captured);
        $pending = $this->payment($order, 300);
        $failed = $this->payment($order, 300, PaymentEvent::Failed);
        $summary = OrderPayments::of($order, [$captured, $pending, $failed]);

        self::assertSame(400, $summary->captured()->minorUnits);
        self::assertSame(400, $summary->net()->minorUnits);
        self::assertSame(300, $summary->payable()->minorUnits);
        self::assertSame(OrderPaymentState::PartiallyPaid, $summary->state());
    }

    public function testAnOrderCapturedInFullIsPaidAndPayableNoMore(): void
    {
        $order = $this->order();
        $summary = OrderPayments::of($order, [$this->payment($order, 600, PaymentEvent::Captured), $this->payment($order, 400, PaymentEvent::Captured)]);

        self::assertSame(1_000, $summary->captured()->minorUnits);
        self::assertTrue($summary->payable()->isZero());
        self::assertSame(OrderPaymentState::Paid, $summary->state());
    }

    /**
     * A refund frees what it gave back: that much may be paid again.
     */
    public function testRefundsAndDisputesLostAreReturned(): void
    {
        $order = $this->order();
        $partly = $this->payment($order, 600, PaymentEvent::Captured);
        $partly->apply(PaymentNotification::of('r1', 'pi', PaymentEvent::Refunded, Money::of(100, Currency::of('CAD'))));

        $lost = $this->payment($order, 400, PaymentEvent::Captured, PaymentEvent::DisputeOpened, PaymentEvent::DisputeLost);
        $summary = OrderPayments::of($order, [$partly, $lost]);

        self::assertSame(1_000, $summary->captured()->minorUnits);
        self::assertSame(500, $summary->returned()->minorUnits);
        self::assertSame(500, $summary->net()->minorUnits);
        self::assertSame(500, $summary->payable()->minorUnits);
        self::assertSame(OrderPaymentState::PartiallyRefunded, $summary->state());
    }

    public function testEverythingReturnedIsRefunded(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, 1_000, PaymentEvent::Captured);
        $payment->apply(PaymentNotification::of('r1', 'pi', PaymentEvent::Refunded, Money::of(1_000, Currency::of('CAD'))));

        $summary = OrderPayments::of($order, [$payment]);

        self::assertTrue($summary->net()->isZero());
        self::assertSame(OrderPaymentState::Refunded, $summary->state());
    }

    /**
     * A payment of more than what is left, made before a change elsewhere, never makes the payable negative.
     */
    public function testThePayableIsNeverNegative(): void
    {
        $order = $this->order();
        $summary = OrderPayments::of($order, [$this->payment($order, 1_000, PaymentEvent::Captured), $this->payment($order, 500)]);

        self::assertTrue($summary->payable()->isZero());
        self::assertFalse($summary->payable()->isNegative());
        self::assertSame(OrderPaymentState::Paid, $summary->state());
    }

    public function testAPaymentOfAnotherOrderIsRefused(): void
    {
        $other = Order::open('2', Currency::of('CAD'));
        $other->add(Sellable::of(SellableReference::of('product', 'x'), 'X', Money::of(1, Currency::of('CAD')), 'goods'), 1, Money::of(1, Currency::of('CAD')));
        $other->place(new DateTimeImmutable());

        $payment = Payment::create($other, 'card', Money::of(1, Currency::of('CAD')), new DateTimeImmutable());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The payment %s is of the order 2, not of 1.', $payment->id));

        OrderPayments::of($this->order(), [$payment]);
    }

    public function testANotificationCarriesWhatItSays(): void
    {
        $refund = PaymentNotification::of('evt_1', 'pi_1', PaymentEvent::Refunded, Money::of(5, Currency::of('CAD')));

        self::assertSame('evt_1', $refund->id);
        self::assertSame('pi_1', $refund->externalReference);
        self::assertSame(PaymentEvent::Refunded, $refund->event);
        self::assertSame(5, $refund->amount?->minorUnits);
        self::assertNull(PaymentNotification::of(str_repeat('a', 255), str_repeat('b', 255), PaymentEvent::Captured)->amount);
        self::assertFalse($refund->isIgnored());
        self::assertNull($refund->ignoredType);
    }

    public function testAnIgnoredNotificationNamesNoPayment(): void
    {
        $ignored = PaymentNotification::ignored('evt_1', 'customer.created');

        self::assertSame('evt_1', $ignored->id);
        self::assertNull($ignored->externalReference);
        self::assertSame(PaymentEvent::Ignored, $ignored->event);
        self::assertNull($ignored->amount);
        self::assertSame('customer.created', $ignored->ignoredType);
        self::assertTrue($ignored->isIgnored());
        self::assertSame(str_repeat('t', 255), PaymentNotification::ignored(str_repeat('a', 255), str_repeat('t', 255))->ignoredType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidIgnoredNotifications(): iterable
    {
        yield 'empty id' => ['', 'customer.created'];
        yield 'id too long' => [str_repeat('a', 256), 'customer.created'];
        yield 'id with a trailing newline' => ["evt\n", 'customer.created'];
        yield 'empty type' => ['evt', ''];
        yield 'type too long' => ['evt', str_repeat('t', 256)];
        yield 'type with a space' => ['evt', 'customer created'];
    }

    #[DataProvider('invalidIgnoredNotifications')]
    public function testAnInvalidIgnoredNotificationIsRefused(string $id, string $type): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A notification id and an event type are 1 to 255 visible ASCII characters.');

        PaymentNotification::ignored($id, $type);
    }

    public function testAnIgnoredEventIsNotMadeByOf(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An ignored notification names no payment: it is made by ignored().');

        PaymentNotification::of('evt', 'pi', PaymentEvent::Ignored);
    }

    /**
     * @return iterable<string, array{string, string, PaymentEvent, ?int}>
     */
    public static function invalidNotifications(): iterable
    {
        yield 'empty id' => ['', 'pi', PaymentEvent::Captured, null];
        yield 'id too long' => [str_repeat('a', 256), 'pi', PaymentEvent::Captured, null];
        yield 'id with a space' => ['evt 1', 'pi', PaymentEvent::Captured, null];
        yield 'id with a trailing newline' => ["evt\n", 'pi', PaymentEvent::Captured, null];
        yield 'empty reference' => ['evt', '', PaymentEvent::Captured, null];
        yield 'reference too long' => ['evt', str_repeat('b', 256), PaymentEvent::Captured, null];
        yield 'reference with a newline' => ['evt', "pi\n", PaymentEvent::Captured, null];
        yield 'a refund without its amount' => ['evt', 'pi', PaymentEvent::Refunded, null];
        yield 'an amount on a capture' => ['evt', 'pi', PaymentEvent::Captured, 5];
    }

    #[DataProvider('invalidNotifications')]
    public function testAnInvalidNotificationIsRefused(string $id, string $reference, PaymentEvent $event, ?int $amount): void
    {
        $this->expectException(InvalidValue::class);

        PaymentNotification::of($id, $reference, $event, null === $amount ? null : Money::of($amount, Currency::of('CAD')));
    }

    public function testAStartTellsHowTheBuyerGoesOn(): void
    {
        $page = PaymentStart::of('pi_1', 'https://pay.example/x?y=1');
        $script = PaymentStart::of(str_repeat('r', 255), null, 'secret_1');

        self::assertSame('pi_1', $page->externalReference);
        self::assertSame('https://pay.example/x?y=1', $page->redirectUrl);
        self::assertNull($page->clientSecret);
        self::assertNull($script->redirectUrl);
        self::assertSame('secret_1', $script->clientSecret);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function invalidStarts(): iterable
    {
        yield 'empty reference' => ['', null, null, 'A payment reference is 1 to 255 visible ASCII characters.'];
        yield 'reference too long' => [str_repeat('r', 256), null, null, 'A payment reference is 1 to 255 visible ASCII characters.'];
        yield 'reference with a newline' => ["pi\n", null, null, 'A payment reference is 1 to 255 visible ASCII characters.'];
        yield 'page over http' => ['pi', 'http://pay.example', null, 'A payment page is an https URL.'];
        yield 'page with a space' => ['pi', 'https://pay.example/a b', null, 'A payment page is an https URL.'];
        yield 'page with a trailing newline' => ['pi', "https://pay.example\n", null, 'A payment page is an https URL.'];
        yield 'page without a host' => ['pi', 'https://', null, 'A payment page is an https URL.'];
        yield 'page with something before the scheme' => ['pi', 'xhttps://pay.example', null, 'A payment page is an https URL.'];
        yield 'empty secret' => ['pi', null, '', 'A client secret, when given, is not empty.'];
    }

    #[DataProvider('invalidStarts')]
    public function testAnInvalidStartIsRefused(string $reference, ?string $page, ?string $secret, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        PaymentStart::of($reference, $page, $secret);
    }

    private function payment(Order $order, int $minorUnits, PaymentEvent ...$events): Payment
    {
        $payment = Payment::create($order, 'card', Money::of($minorUnits, Currency::of('CAD')), new DateTimeImmutable());

        foreach ($events as $event) {
            $payment->apply(PaymentNotification::of('evt_'.++$this->sequence, 'pi', $event));
        }

        return $payment;
    }

    private function order(): Order
    {
        $order = Order::open('1', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', Money::of(1_000, Currency::of('CAD')), 'goods'), 1, Money::of(1_000, Currency::of('CAD')));
        $order->place(new DateTimeImmutable());

        return $order;
    }
}
