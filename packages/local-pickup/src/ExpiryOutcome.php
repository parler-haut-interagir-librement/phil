<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

/**
 * What becomes of a pickup nobody collected before its hold period ended, as the application configures it.
 */
enum ExpiryOutcome: string
{
    /** The order is cancelled and refunded, through the ExpiredPickupCanceller port. */
    case CancelAndRefund = 'cancel_and_refund';

    /** The goods go back to stock and the order stays, through the ExpiredPickupRestocker port. */
    case Restock = 'restock';
}
