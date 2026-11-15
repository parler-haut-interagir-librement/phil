<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Deferred;

/**
 * Where a deferred sale stands.
 *
 *     awaiting deposit → awaiting balance → balance due → settled
 *     awaiting deposit, awaiting balance → settled (paid in full early)
 *     awaiting deposit, awaiting balance, balance due → cancelled
 */
enum DeferredSaleState: string
{
    /** Opened: the deposit is still to pay. */
    case AwaitingDeposit = 'awaiting_deposit';

    /** The deposit is paid; the balance waits for its trigger. */
    case AwaitingBalance = 'awaiting_balance';

    /** The trigger came: the balance is to pay now. */
    case BalanceDue = 'balance_due';

    /** Paid in full: nothing is deferred any more. */
    case Settled = 'settled';

    /** Given up before it was paid in full: nothing changes any more. */
    case Cancelled = 'cancelled';

    /** Whether nothing moves any more. */
    public function isClosed(): bool
    {
        return self::Settled === $this || self::Cancelled === $this;
    }
}
