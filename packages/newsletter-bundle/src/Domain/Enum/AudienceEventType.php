<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * What happened to a subscriber, for listeners, automations and outgoing webhooks.
 */
enum AudienceEventType: string
{
    case Subscribed = 'subscribed';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Erased = 'erased';
}
