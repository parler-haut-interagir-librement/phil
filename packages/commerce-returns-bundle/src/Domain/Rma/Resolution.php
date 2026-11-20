<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\Rma;

/**
 * How a return is settled.
 */
enum Resolution: string
{
    /** The money goes back to the order's payments. */
    case Refund = 'refund';

    /** The value becomes the customer's store credit. */
    case StoreCredit = 'store_credit';

    /** The value pays another order; what is left is refunded. */
    case Exchange = 'exchange';
}
