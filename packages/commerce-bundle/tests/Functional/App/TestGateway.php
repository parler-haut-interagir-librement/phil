<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

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
 * A processor for the method `card`, as the application would plug one in: it names a payment after its id, and
 * reads JSON notifications signed with an HMAC of the body under a shared secret.
 */
final readonly class TestGateway implements PaymentGateway
{
    public const string SECRET = 'whsec_test';

    #[Override]
    public function supports(string $method): bool
    {
        return 'card' === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of('pi_'.$payment->id, null, 'secret_'.$payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        if (!hash_equals(hash_hmac('sha256', $payload, self::SECRET), $headers['x-test-signature'][0] ?? '')) {
            throw InvalidNotification::because('The signature does not verify.');
        }

        /** @var array{id: string, payment: string, type: string} $event */
        $event = json_decode($payload, true, flags: \JSON_THROW_ON_ERROR);

        return PaymentNotification::of($event['id'], $event['payment'], PaymentEvent::from($event['type']));
    }
}
