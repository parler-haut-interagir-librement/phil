<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Payment;

/**
 * Where a payment stands. Every move after `New` comes from a notification of the processor, never from the
 * browser's return to the store.
 */
enum PaymentState: string
{
    /** Created for an amount of a placed order; the processor has not been asked yet. */
    case New = 'new';

    /** Handed to the processor, which awaits the buyer or its own checks. */
    case Processing = 'processing';

    /** Authorized by the processor, not captured yet. */
    case Authorized = 'authorized';

    /** Captured: the money is the seller's. Partial refunds leave it here. */
    case Completed = 'completed';

    /** Refused by the processor or abandoned by the buyer. */
    case Failed = 'failed';

    /** Cancelled before capture. */
    case Cancelled = 'cancelled';

    /**
     * Given up before the processor settled it — the buyer left the processor's page and no notification came:
     * it no longer holds its share of the order. A late notification still applies to it, so money the processor
     * captures after all is recorded, and shows as overpaid when the order was paid otherwise in the meantime.
     */
    case Abandoned = 'abandoned';

    /** Refunded in full. */
    case Refunded = 'refunded';

    /** Contested by the buyer with their bank; the processor holds the money until the dispute ends. */
    case Disputed = 'disputed';

    /** A dispute lost: the money went back to the buyer. */
    case ChargedBack = 'charged_back';
}
