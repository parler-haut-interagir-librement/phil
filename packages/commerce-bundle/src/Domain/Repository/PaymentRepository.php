<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Repository;

use Phil\CommerceBundle\Domain\Model\Payment;

/**
 * Finds and stores payments. `Infrastructure\Doctrine\DoctrinePaymentRepository` is the Doctrine adapter.
 */
interface PaymentRepository
{
    public function find(string $id): ?Payment;

    /**
     * The payment a processor's notification names, by the processor's own reference.
     */
    public function findByExternalReference(string $method, string $externalReference): ?Payment;

    /**
     * @return list<Payment> every payment of the order, oldest first
     */
    public function forOrder(string $orderNumber): array;

    public function add(Payment $payment): void;
}
