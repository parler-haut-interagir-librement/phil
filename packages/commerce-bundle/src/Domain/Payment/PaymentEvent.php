<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Payment;

/**
 * What a processor's notification says happened to a payment, once its gateway has verified and read it.
 */
enum PaymentEvent: string
{
    case Processing = 'processing';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** Carries the amount refunded by this notification, part or all of what is left. */
    case Refunded = 'refunded';

    case DisputeOpened = 'dispute_opened';
    case DisputeWon = 'dispute_won';
    case DisputeLost = 'dispute_lost';

    /**
     * A verified notification of an event the store does not act on: it names no payment, changes nothing, and is
     * acknowledged so that the processor stops sending it.
     */
    case Ignored = 'ignored';
}
