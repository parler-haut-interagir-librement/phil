<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Repository\PaymentRepository;

/**
 * Payments in memory, in the order they were added.
 */
final class InMemoryPayments implements PaymentRepository
{
    /** @var list<Payment> */
    public array $payments = [];

    #[Override]
    public function find(string $id): ?Payment
    {
        return array_find($this->payments, static fn (Payment $payment): bool => $payment->id === $id);
    }

    #[Override]
    public function findByExternalReference(string $method, string $externalReference): ?Payment
    {
        return array_find($this->payments, static fn (Payment $payment): bool => $payment->method === $method && $payment->externalReference() === $externalReference);
    }

    #[Override]
    public function forOrder(string $orderNumber): array
    {
        return array_values(array_filter($this->payments, static fn (Payment $payment): bool => $payment->orderNumber === $orderNumber));
    }

    #[Override]
    public function add(Payment $payment): void
    {
        $this->payments[] = $payment;
    }
}
