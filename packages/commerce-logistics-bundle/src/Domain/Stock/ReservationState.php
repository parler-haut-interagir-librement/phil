<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Stock;

/**
 * Where a reservation of stock for an order stands.
 */
enum ReservationState: string
{
    /** Set aside for the order: on hand, no longer available. */
    case Held = 'held';

    /** Given back: the order was cancelled, or its line changed. */
    case Released = 'released';

    /** Gone with the order: off the shelf. */
    case Shipped = 'shipped';
}
