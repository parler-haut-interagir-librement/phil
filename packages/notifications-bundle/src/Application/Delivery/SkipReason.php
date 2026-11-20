<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * Why a channel of a notification type did not take it for one recipient.
 */
enum SkipReason: string
{
    /** The recipient turned the channel off, for the type or for every type. */
    case OptedOut = 'opted_out';

    /** The recipient's quiet hours hold the channel back. */
    case QuietHours = 'quiet_hours';

    /** The recipient directory has no address for the channel, or the recipient subscribed no browser. */
    case NoContact = 'no_contact';

    /** The circuit breaker of the channel, or of its transport, is open. */
    case CircuitOpen = 'circuit_open';

    /** The transport or the push service refused it. */
    case Refused = 'refused';

    /** No sender of the application or of the bundle serves the channel. */
    case Unavailable = 'unavailable';

    /**
     * Whether trying again later may succeed: a refusal or an open circuit is a provider's state, the others are the
     * recipient's or the application's.
     */
    public function isTransient(): bool
    {
        return self::Refused === $this || self::CircuitOpen === $this;
    }
}
