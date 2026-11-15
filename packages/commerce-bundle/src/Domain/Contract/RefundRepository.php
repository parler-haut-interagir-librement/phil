<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use Phil\CommerceBundle\Domain\Model\Refund;

/**
 * Finds and stores refunds. `Infrastructure\Doctrine\DoctrineRefundRepository` is the Doctrine adapter.
 */
interface RefundRepository
{
    public function findRefund(string $id): ?Refund;

    /**
     * @return list<Refund> the payment's refunds, oldest first
     */
    public function refundsOf(string $paymentId): array;

    public function addRefund(Refund $refund): void;
}
