<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Stock;

/**
 * Why a layer of stock moved.
 */
enum EntryKind: string
{
    /** Units received, bought or on consignment. */
    case Received = 'received';
    /** Units already on hand whose owner the ledger starts to keep. */
    case Adopted = 'adopted';
    case Sold = 'sold';
    /** Units sold and brought back. */
    case Returned = 'returned';
    case TransferredOut = 'transferred_out';
    case TransferredIn = 'transferred_in';
    /** The difference a count found. */
    case Counted = 'counted';
    /** Unsold units given back to their consignor. */
    case GivenBack = 'given_back';
    /** A consignor's units the shop bought, at the agreed cost: they leave the consignor's layer. */
    case BoughtOut = 'bought_out';
    /** The same units, in the shop's own layer they reach. */
    case BoughtIn = 'bought_in';
}
