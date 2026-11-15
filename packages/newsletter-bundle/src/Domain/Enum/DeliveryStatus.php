<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * What became of one message to one subscriber.
 */
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';

    /** Not sent: the subscriber left, paused or is suppressed. */
    case Skipped = 'skipped';
    case Failed = 'failed';
    case Bounced = 'bounced';
}
