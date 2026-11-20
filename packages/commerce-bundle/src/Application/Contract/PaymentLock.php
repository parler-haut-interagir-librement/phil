<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

use LogicException;
use Phil\CommerceBundle\Domain\Model\Payment;
use Phil\CommerceBundle\Domain\Model\Refund;

/**
 * Holds a payment for one refund at a time, inside the transaction that stores the refund: two refunds granted at once
 * on the same payment wait for each other, and the second one reads what the first one granted.
 * `Infrastructure\Doctrine\DoctrinePaymentLock` is the Doctrine adapter.
 */
interface PaymentLock
{
    /**
     * Locks the payment for writing until the transaction ends, reads it again from the store, and gives its refunds
     * as the store holds them once the lock is taken, oldest first.
     *
     * @return list<Refund>
     *
     * @throws LogicException when no transaction is open, since the lock would end with the statement
     */
    public function lockForRefund(Payment $payment): array;
}
