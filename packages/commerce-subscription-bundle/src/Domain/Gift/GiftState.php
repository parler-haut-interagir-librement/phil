<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Gift;

/**
 * Where a gift stands.
 */
enum GiftState: string
{
    /** Bought, waiting for the payment of its order: no code yet. */
    case Pending = 'pending';

    /** Paid: its code redeems it until it expires. */
    case Paid = 'paid';

    /** Redeemed by its beneficiary, whose subscription it started. */
    case Redeemed = 'redeemed';

    /** Given up before it was redeemed, or never redeemed before it expired. */
    case Cancelled = 'cancelled';
}
