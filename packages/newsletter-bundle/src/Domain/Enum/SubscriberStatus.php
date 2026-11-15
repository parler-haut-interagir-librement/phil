<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * What a subscriber is to the site's newsletter as a whole.
 */
enum SubscriberStatus: string
{
    /** Waiting for the confirmation of a double opt-in. */
    case Pending = 'pending';

    /** Receives what their lists send. */
    case Active = 'active';

    /** Receives nothing until the pause ends. */
    case Paused = 'paused';

    /** Left every list. */
    case Unsubscribed = 'unsubscribed';

    /** Their address no longer receives mail. */
    case Bounced = 'bounced';

    /** Reported a message as spam: never mailed again. */
    case Complained = 'complained';
}
