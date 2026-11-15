<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\OrderPaymentState;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Money;

/**
 * An order and its payments, summed: what was captured, what went back, what is still to pay and what may still be
 * asked of a new payment.
 */
final readonly class OrderPayments
{
    /**
     * @param list<Payment> $payments
     */
    private function __construct(
        public Money $total,
        public array $payments,
    ) {
    }

    /**
     * @param list<Payment> $payments the order's payments, every one of them
     */
    public static function of(Order $order, array $payments): self
    {
        foreach ($payments as $payment) {
            if ($payment->orderNumber !== $order->number) {
                throw InvalidValue::because(\sprintf('The payment %s is of the order %s, not of %s.', $payment->id, $payment->orderNumber, $order->number));
            }
        }

        return new self($order->total(), $payments);
    }

    /**
     * What the processors captured, whatever happened to it since.
     */
    public function captured(): Money
    {
        $sum = Money::zero($this->total->currency);

        foreach ($this->payments as $payment) {
            if ($payment->wasCaptured()) {
                $sum = $sum->add($payment->amount);
            }
        }

        return $sum;
    }

    /**
     * What went back to the buyer: refunds, and disputes lost.
     */
    public function returned(): Money
    {
        $sum = Money::zero($this->total->currency);

        foreach ($this->payments as $payment) {
            $sum = $sum->add(PaymentState::ChargedBack === $payment->state() ? $payment->amount : $payment->refunded);
        }

        return $sum;
    }

    /**
     * What the seller keeps: captured less returned.
     */
    public function net(): Money
    {
        return $this->captured()->subtract($this->returned());
    }

    /**
     * What a new payment may still ask: the total less every payment that holds, or may still hold, money.
     */
    public function payable(): Money
    {
        $sum = $this->total;

        foreach ($this->payments as $payment) {
            if ($payment->counts()) {
                $sum = $sum->subtract($payment->amount->subtract($payment->refunded));
            }
        }

        return $sum->isNegative() ? Money::zero($sum->currency) : $sum;
    }

    /**
     * What the payments that count hold beyond the total: an order amended down after it was paid.
     */
    public function overpaid(): Money
    {
        $held = Money::zero($this->total->currency);

        foreach ($this->payments as $payment) {
            if ($payment->counts()) {
                $held = $held->add($payment->amount->subtract($payment->refunded));
            }
        }

        $over = $held->subtract($this->total);

        return $over->isPositive() ? $over : Money::zero($over->currency);
    }

    public function state(): OrderPaymentState
    {
        if ($this->captured()->isZero()) {
            return OrderPaymentState::Unpaid;
        }

        if ($this->returned()->isZero()) {
            return $this->net()->isLessThan($this->total) ? OrderPaymentState::PartiallyPaid : OrderPaymentState::Paid;
        }

        return $this->net()->isZero() ? OrderPaymentState::Refunded : OrderPaymentState::PartiallyRefunded;
    }
}
