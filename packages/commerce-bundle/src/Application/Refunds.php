<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Contract\RefundRepository;
use Phil\CommerceBundle\Domain\Event\OrderRefunded;
use Phil\CommerceBundle\Domain\Exception\PaymentRefused;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;
use Phil\CommerceBundle\Domain\Payment\PaymentState;
use Phil\CommerceBundle\Domain\Refund\RefundState;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;
use Phil\Money\Money;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Refunds in two steps, granted then executed, each by someone the application allows — two rights, which a small
 * store merges with `phil_commerce.refunds.single_step`, where granting executes at once.
 *
 * - **What is granted never exceeds what is left**: the payment's refundable amount, less the refunds granted and not
 *   executed yet.
 * - **Executing asks the payment's processor**, or records an offline refund, through `Payments::refund()`.
 * - **A refund executed is announced once** by `OrderRefunded`, with the order's channel and customer when the order
 *   is found — what an activity feed or a loyalty programme listens to.
 *
 * Nothing is committed here: the application commits its unit of work.
 */
final readonly class Refunds
{
    public function __construct(
        private RefundRepository $refunds,
        private PaymentRepository $payments,
        private Payments $gateway,
        private bool $singleStep = false,
        private ?OrderRepository $orders = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public function grant(Payment $payment, Money $amount, string $reason, string $grantedBy, DateTimeImmutable $at): Refund
    {
        if (PaymentState::Completed !== $payment->state()) {
            throw PaymentRefused::notRefundable($payment->id, $payment->state());
        }

        $refund = Refund::grant($payment, $amount, $reason, $grantedBy, $at);
        $left = $payment->refundable();

        foreach ($this->refunds->refundsOf($payment->id) as $granted) {
            if (RefundState::Granted === $granted->state()) {
                $left = $left->subtract($granted->amount);
            }
        }

        if (!$amount->isPositive() || $amount->isGreaterThan($left)) {
            throw PaymentRefused::amount('A refund', $amount->toDecimal().' '.$amount->currency->code, $left->toDecimal().' '.$left->currency->code);
        }

        $this->refunds->addRefund($refund);

        if ($this->singleStep) {
            $this->execute($refund, $grantedBy, $at);
        }

        return $refund;
    }

    public function execute(Refund $refund, string $executedBy, DateTimeImmutable $at): void
    {
        if (RefundState::Granted !== $refund->state()) {
            throw PaymentRefused::refundSettled($refund->id, $refund->state()->value);
        }

        $payment = $this->payments->find($refund->paymentId) ?? throw PaymentRefused::refundSettled($refund->id, 'without its payment');
        $this->gateway->refund($payment, $refund->amount);
        $refund->execute($executedBy, $at);

        if ($this->dispatcher instanceof EventDispatcherInterface) {
            $order = $this->orders?->find($refund->orderNumber);
            $this->dispatcher->dispatch(new OrderRefunded(
                $refund->orderNumber,
                $refund->id,
                $refund->paymentId,
                $order instanceof Order ? $order->channel() : null,
                $order instanceof Order ? $order->customer() : null,
                $refund->amount,
                $at,
            ));
        }
    }

    public function withdraw(Refund $refund, DateTimeImmutable $at): void
    {
        $refund->withdraw($at);
    }

    public function isSingleStep(): bool
    {
        return $this->singleStep;
    }
}
