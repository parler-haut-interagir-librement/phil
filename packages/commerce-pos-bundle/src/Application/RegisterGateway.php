<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

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
 * The core's gateway for what the register takes itself: cash, and the methods it records — a card on a terminal
 * that is not connected, a cheque. The register is its own processor: a payment's reference is its id, and a refund,
 * made at the register, is recorded at once, under its idempotency key: asked again, it is recorded once. It receives
 * no notification.
 */
final readonly class RegisterGateway implements PaymentGateway
{
    public const string CASH = 'cash';

    /**
     * @param list<string> $recordedMethods
     */
    public function __construct(
        private array $recordedMethods,
    ) {
    }

    public function records(string $method): bool
    {
        return \in_array($method, $this->recordedMethods, true);
    }

    #[Override]
    public function supports(string $method): bool
    {
        return self::CASH === $method || $this->records($method);
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
        throw InvalidNotification::because('The register is its own processor: it sends no notification.');
    }
}
