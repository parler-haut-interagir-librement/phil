<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Payment\PaymentEvent;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\Money\Money;
use RuntimeException;

/**
 * A card processor for the tests, which refunds at once — or fails, for the payment named in `$failing`.
 */
final class CardGateway implements PaymentGateway
{
    public ?string $failing = null;

    /** @var list<string> the idempotency key of each refund asked */
    public array $idempotencyKeys = [];

    private int $notifications = 0;

    #[Override]
    public function supports(string $method): bool
    {
        return 'card' === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of('ch_'.$payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        $this->idempotencyKeys[] = $idempotencyKey;

        if ($payment->id === $this->failing) {
            throw new RuntimeException('The processor is unreachable.');
        }

        $payment->apply(PaymentNotification::of('n'.++$this->notifications, (string) $payment->externalReference(), PaymentEvent::Refunded, $amount));
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        throw InvalidNotification::because('No notification in the tests.');
    }

    public function capture(Payment $payment): void
    {
        $payment->apply(PaymentNotification::of('n'.++$this->notifications, (string) $payment->externalReference(), PaymentEvent::Captured));
    }
}
