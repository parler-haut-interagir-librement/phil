<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application\Contract;

use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommercePosBundle\Application\TerminalCharge;
use Phil\Money\Money;

/**
 * Speaks to one processor's connected terminals, from the server: shows an amount on a device, cancels it, refunds a
 * payment it took, and reads the processor's signed notifications. One adapter per processor, in the application or
 * a package of its own; the till runs no script of the processor, and the processor's secret key stays on the server.
 *
 * A service implementing it is tagged `phil_commerce_pos.payment_terminal` by autoconfiguration.
 */
interface PaymentTerminal
{
    /**
     * The adapter's name, as a terminal names it: up to 32 lowercase letters, digits or "_".
     */
    public function name(): string;

    /**
     * Asks the processor to show the charge on the device. The processor carries the charge's payment id and gives it
     * back in its notifications.
     *
     * @return string the processor's own reference of the request, 1 to 255 visible ASCII characters
     */
    public function present(TerminalCharge $charge): string;

    /**
     * Withdraws a request the device still shows.
     */
    public function cancel(string $device, string $processorReference): void;

    /**
     * Asks the processor to refund part or all of a payment the device took. The refund is recorded when the
     * processor's notification says it happened.
     *
     * @param string $idempotencyKey the same for every try of one refund, 1 to 255 visible ASCII characters: hand it
     *                               to the processor as its idempotency key, so that a refund asked again — after a
     *                               timeout, a crash, a second worker — is made once
     */
    public function refund(string $processorReference, Money $amount, string $idempotencyKey): void;

    /**
     * Whether a notification is the processor's, by the headers it always sends — its signature header.
     *
     * @param array<string, list<string>> $headers the request's headers, names in lowercase
     */
    public function recognises(array $headers): bool;

    /**
     * Verifies a notification as the processor signed it and reads it; its external reference is the payment id the
     * charge carried.
     *
     * @param array<string, list<string>> $headers the request's headers, names in lowercase
     *
     * @throws InvalidNotification when the signature does not verify or the body is not one of the processor's events
     */
    public function read(string $payload, array $headers): PaymentNotification;
}
