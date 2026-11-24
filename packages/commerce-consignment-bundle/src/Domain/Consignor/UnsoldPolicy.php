<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Consignor;

/**
 * What happens to a consignor's unsold units when their consignment ends.
 */
enum UnsoldPolicy: string
{
    /** They go back to the consignor. */
    case ReturnToConsignor = 'return';
    /** The consignment runs on for the agreed extensions, then they go back to the consignor. */
    case Extend = 'extend';
    /** The shop buys them, at the cost agreed. */
    case BuyOut = 'buy_out';
}
