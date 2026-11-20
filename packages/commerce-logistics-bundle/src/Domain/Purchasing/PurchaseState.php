<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Purchasing;

/**
 * Where a purchase order stands.
 */
enum PurchaseState: string
{
    /** Sent to the supplier; some units still expected. */
    case Open = 'open';

    /** Every unit ordered was received. */
    case Received = 'received';

    /** Closed before every unit came: nothing more is expected. */
    case Closed = 'closed';
}
