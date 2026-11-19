<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\Money\Money;

/**
 * The core's gateway for the method `exchange`: the value of returned goods paying another order. It is its own
 * processor: a payment's reference is its id, and a refund is recorded at once, under its idempotency key, so that a
 * refund asked again is recorded once — giving that value back, as store credit for instance, is the application's
 * decision. It receives no notification.
 */
final readonly class ReturnsGateway implements PaymentGateway
{
    public const string EXCHANGE = 'exchange';

    #[Override]
    public function supports(string $method): bool
    {
        return self::EXCHANGE === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of($payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        $payment->apply(PaymentNotification::of($idempotencyKey, $payment->id, PaymentEvent::Refunded, $amount));
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        throw InvalidNotification::because('An exchange is its own processor: it sends no notification.');
    }
}
