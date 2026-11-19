<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

/**
 * What a subscription did. A form shows the same answer whatever it is, so that nobody learns from it whether an
 * address is subscribed.
 */
enum SubscribeOutcome: string
{
    /** A double opt-in: the confirmation was sent. */
    case ConfirmationSent = 'confirmation_sent';

    /** A double opt-in whose confirmation went to the same subscriber within the delay: none was sent again. */
    case ConfirmationDelayed = 'confirmation_delayed';

    /** Subscribed at once. */
    case Subscribed = 'subscribed';

    /** Already on the list: nothing changed. */
    case AlreadySubscribed = 'already_subscribed';

    /** An address never to mail again: nothing was sent. */
    case Suppressed = 'suppressed';
}
