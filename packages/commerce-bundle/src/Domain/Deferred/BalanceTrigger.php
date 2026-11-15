<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Deferred;

/**
 * What calls the balance of a deferred sale, once its deposit is paid.
 */
enum BalanceTrigger: string
{
    /** A date: the sale's one due date, reached. */
    case Date = 'date';

    /** The goods ship, as the logistics module says. */
    case Shipment = 'shipment';

    /** The goods are available — a preordered product arrives —, as the catalogue or the logistics module says. */
    case Availability = 'availability';

    /** Someone of the shop calls it, or the buyer comes back to pay. */
    case Manual = 'manual';
}
