<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Order;

/**
 * Where a quote stands.
 */
enum QuoteState: string
{
    /** Offered, awaiting the buyer's answer. */
    case Pending = 'pending';

    /** Accepted: its order is placed. */
    case Accepted = 'accepted';

    /** Declined by the buyer, or withdrawn: its order is a cart again. */
    case Declined = 'declined';
}
