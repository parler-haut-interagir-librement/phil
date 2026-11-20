<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Domain\Instrument;

/**
 * What holds stored value.
 */
enum InstrumentKind: string
{
    /** A card known by its code, to whoever holds it. */
    case GiftCard = 'gift_card';

    /** A customer's credit at the shop, known by the customer. */
    case StoreCredit = 'store_credit';
}
