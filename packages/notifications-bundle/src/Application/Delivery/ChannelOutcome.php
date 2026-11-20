<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * What one attempt on one channel gave.
 */
enum ChannelOutcome
{
    /** The transport or the push service accepted the message: accepted by the provider, not yet delivered. */
    case Accepted;

    /** The recipient cannot be reached on the channel: no address, or no subscription the push service still knows. */
    case Unreachable;

    /** The transport or the push service refused the message, or did not answer. */
    case Refused;
}
