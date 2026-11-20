<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\PaymentGateway;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\ValueObject\PaymentNotification;
use Phil\CommerceBundle\Domain\ValueObject\PaymentStart;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\CommercePosBundle\Domain\Model\TerminalPayment;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\Money\Money;

/**
 * The core's gateway for the payments taken on connected terminals, under one method — `card_present` by default.
 * A payment's reference is its own id, which the adapter hands to the processor and reads back from its
 * notifications; `TerminalPayments` presents it on the device once the core created it.
 */
final readonly class TerminalGateway implements PaymentGateway
{
    public function __construct(
        private TerminalAdapters $adapters,
        private RegisterRepository $registers,
        private RegisterSaleRepository $sales,
        private string $method = 'card_present',
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    #[Override]
    public function supports(string $method): bool
    {
        return $this->method === $method;
    }

    #[Override]
    public function start(Payment $payment, Order $order): PaymentStart
    {
        return PaymentStart::of($payment->id);
    }

    #[Override]
    public function refund(Payment $payment, Money $amount, string $idempotencyKey): void
    {
        $presented = $this->sales->findTerminalPayment($payment->id);

        if (!$presented instanceof TerminalPayment) {
            throw PosRefused::notPresented($payment->id);
        }

        $terminal = $this->registers->findTerminal($presented->terminal) ?? throw PosRefused::unknownTerminal($presented->terminal);
        $this->adapters->named($terminal->adapter)->refund($presented->processorReference, $amount, $idempotencyKey);
    }

    #[Override]
    public function read(string $payload, array $headers): PaymentNotification
    {
        return $this->adapters->recognising($headers)->read($payload, $headers);
    }
}
