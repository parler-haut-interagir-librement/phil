<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\Model;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Refund\RefundState;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Refund::class)]
final class RefundTest extends TestCase
{
    public function testARefundIsGrantedThenExecuted(): void
    {
        $payment = $this->payment();
        $grantedAt = new DateTimeImmutable('2026-11-11T10:00:00Z');
        $executedAt = new DateTimeImmutable('2026-11-11T11:00:00Z');
        $refund = Refund::grant($payment, $this->cad(250), 'Broken die', 'clerk-1', $grantedAt);

        self::assertSame($payment->id, $refund->paymentId);
        self::assertSame('2026-0001', $refund->orderNumber);
        self::assertSame(250, $refund->amount->minorUnits);
        self::assertSame('CAD', $refund->amount->currency->code);
        self::assertSame('Broken die', $refund->reason);
        self::assertSame('clerk-1', $refund->grantedBy);
        self::assertSame($grantedAt, $refund->grantedAt);
        self::assertSame(RefundState::Granted, $refund->state());
        self::assertNull($refund->executedBy());
        self::assertNull($refund->settledAt());

        $refund->execute('manager-1', $executedAt);

        self::assertSame(RefundState::Executed, $refund->state());
        self::assertSame('manager-1', $refund->executedBy());
        self::assertSame($executedAt, $refund->settledAt());
    }

    public function testAWithdrawnRefundIsSettled(): void
    {
        $refund = Refund::grant($this->payment(), $this->cad(250), 'Broken die', 'clerk-1', new DateTimeImmutable());
        $at = new DateTimeImmutable('2026-11-11T12:00:00Z');

        $refund->withdraw($at);
        self::assertSame(RefundState::Withdrawn, $refund->state());
        self::assertSame($at, $refund->settledAt());

        $this->expectException(PaymentRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The refund %s is withdrawn: only a granted refund is executed or withdrawn.', $refund->id));

        $refund->execute('manager-1', $at);
    }

    public function testAnExecutedRefundIsNotWithdrawn(): void
    {
        $refund = Refund::grant($this->payment(), $this->cad(250), 'Broken die', 'clerk-1', new DateTimeImmutable());
        $refund->execute('manager-1', new DateTimeImmutable());

        $this->expectException(PaymentRefused::class);

        $refund->withdraw(new DateTimeImmutable());
    }

    public function testARefundIsInThePaymentsCurrency(): void
    {
        $this->expectException(PaymentRefused::class);

        Refund::grant($this->payment(), Money::of(1, Currency::of('USD')), 'Broken die', 'clerk-1', new DateTimeImmutable());
    }

    public function testARefundHasAReason(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A refund has a reason: 1 to 255 characters on one line.');

        Refund::grant($this->payment(), $this->cad(1), " \n", 'clerk-1', new DateTimeImmutable());
    }

    private function payment(): Payment
    {
        $order = Order::open('2026-0001', Currency::of('CAD'));
        $order->add(Sellable::of(SellableReference::of('product', 'dice'), 'Dice', $this->cad(1_000), 'goods'), 1, $this->cad(1_000));
        $order->place(new DateTimeImmutable());

        return Payment::create($order, 'card', $this->cad(1_000), new DateTimeImmutable());
    }

    private function cad(int $cents): Money
    {
        return Money::of($cents, Currency::of('CAD'));
    }
}
