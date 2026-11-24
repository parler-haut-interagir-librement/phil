<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Cost;

/**
 * Why the units on hand differ from what the journal expected: what a shrink report breaks its value down by.
 */
enum VarianceReason: string
{
    /** Broken or spoiled on the shelf, in the stockroom or in handling. */
    case Damaged = 'damaged';

    /** Stolen, by a customer or anyone else. */
    case Theft = 'theft';

    /** Gone without a known cause. */
    case Lost = 'lost';

    /** Past its date: no longer sold. */
    case Expired = 'expired';

    /** An earlier count, receipt or shipment counted wrong: the units were never there, or were. */
    case CountError = 'count_error';

    /** The supplier sent fewer, more or other units than its papers say. */
    case SupplierError = 'supplier_error';

    /** Taken off the shelf for the shop's own use: a demo, a sample, a display. */
    case InternalUse = 'internal_use';

    /** Given away: a donation, a prize. */
    case Donated = 'donated';
}
