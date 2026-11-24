<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Cost;

/**
 * Why the value of a SKU at a location moved, in the cost journal.
 */
enum CostEntryKind: string
{
    /** Units came in: a receipt, against a purchase order or not. */
    case Received = 'received';

    /** The share of a landed cost — freight, duties — added to the units of a receipt. */
    case Landed = 'landed';

    /** Units left with an order: the cost of the sale. */
    case Issued = 'issued';

    /** Units a customer sent back, at the cost they left at. */
    case Returned = 'returned';

    /** A count or an adjustment found units missing or extra. */
    case Variance = 'variance';

    case TransferredOut = 'transferred_out';

    case TransferredIn = 'transferred_in';
}
