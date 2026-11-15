<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Bounces;

/**
 * What a bounce did to a subscriber.
 */
enum BounceOutcome: string
{
    /** The address no longer receives mail: suppressed. */
    case Bounced = 'bounced';

    /** A soft bounce, counted. */
    case Counted = 'counted';

    /** A spam report: suppressed. */
    case Complained = 'complained';

    /** A request to leave, by mail or from the provider. */
    case Unsubscribed = 'unsubscribed';

    /** Nothing to do: an automatic reply, a report that names no subscriber, a message to review. */
    case Ignored = 'ignored';
}
