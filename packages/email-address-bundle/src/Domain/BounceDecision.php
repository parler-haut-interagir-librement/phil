<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Domain;

/**
 * What the bounce rules decided for an address. The application maps each to its own effects.
 */
enum BounceDecision: string
{
    /** Stop sending to the address: a hard bounce, or soft bounces up to the threshold within the window. */
    case Suspend = 'suspend';
    /** A soft bounce counted, below the threshold: nothing changes yet. */
    case Count = 'count';
    /** Unsubscribe the address from what it was sent: an authenticated complaint or a request to leave. */
    case Unsubscribe = 'unsubscribe';
    /** Keep the notice for a person to read: a complaint no authenticator vouched for, a report not understood. */
    case Review = 'review';
    /** The same notice was decided before: nothing is done again and no event is dispatched. */
    case Duplicate = 'duplicate';
}
