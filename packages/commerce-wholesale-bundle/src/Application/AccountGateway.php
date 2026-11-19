<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommerceWholesaleBundle\Domain\Model\Receivable;
use Phil\CommerceWholesaleBundle\Domain\Repository\CreditRepository;
use Phil\Money\Money;

/**
 * The core's gateway for the method `on_account`: a purchase on the buyer's credit. A refund credits the
 * receivable back, up to what is outstanding, and lowers what the buyer owes; what the buyer already paid beyond that
 * is kept on the receivable as owed back to the buyer (`Receivable::refundDue()`). It is recorded at once, under its
 * idempotency key: asked again, it credits nothing more. It receives no notification.
 */
final readonly class AccountGateway implements PaymentGateway
{
    public const string ON_ACCOUNT = 'on_account';

    public function __construct(
        private CreditRepository $credit,
    ) {
    }

    #[Override]
    public function supports(string $method): bool
    {
        return self::ON_ACCOUNT === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of($payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        // The refund is recorded on the payment first: a key already applied credits nothing a second time.
        if (!$payment->apply(PaymentNotification::of($idempotencyKey, $payment->id, PaymentEvent::Refunded, $amount))) {
            return;
        }

        $receivable = $this->credit->receivableOfPayment($payment->id);

        if ($receivable instanceof Receivable) {
            $credited = $receivable->refund($amount);

            if ($credited->isPositive()) {
                $this->credit->findAccount($receivable->buyer)?->reduce($credited);
            }
        }
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        throw InvalidNotification::because('A purchase on account is its own processor: it sends no notification.');
    }
}
