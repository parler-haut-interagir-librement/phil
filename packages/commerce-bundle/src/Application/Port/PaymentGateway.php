<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\Money\Money;

/**
 * Speaks to one payment processor: hands it a payment, asks it for a refund, and reads its notifications. The
 * adapter belongs to the application, or to a package of its own, one per processor.
 *
 * A service implementing it is tagged `phil_commerce.payment_gateway` by autoconfiguration.
 */
interface PaymentGateway
{
    /**
     * Whether the gateway answers for this method, as a payment names it (`card`, `interac`...); exactly one should.
     */
    public function supports(string $method): bool;

    /**
     * Hands the payment to the processor, for its amount — never an amount taken from the request.
     */
    public function start(Payment $payment, Order $order): PaymentStart;

    /**
     * Asks the processor to refund part or all of a completed payment. The refund is recorded when the processor's
     * notification says it happened, not when it is asked.
     *
     * @param string $idempotencyKey the same for every try of one refund: hand it to the processor as its idempotency
     *                               key, so that a refund asked again — after a timeout, a crash, a second worker — is
     *                               made once
     */
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void;

    /**
     * Verifies a notification as the processor signed it and reads it.
     *
     * @param array<string, list<string>> $headers the request's headers, names in lowercase
     *
     * @throws InvalidNotification when the signature does not verify or the body is not one of the processor's events
     */
    public function read(string $payload, array $headers): PaymentNotification;
}
