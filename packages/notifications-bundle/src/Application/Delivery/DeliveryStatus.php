<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * How the delivery of a notification to one recipient ended.
 */
enum DeliveryStatus: string
{
    /** The centre kept it, or a channel accepted it, or both: nothing was left to try. */
    case Sent = 'sent';

    /** The same notification was already delivered to the recipient within its window; nothing was sent again. */
    case Duplicate = 'duplicate';

    /** The recipient reached their limit: the centre kept it, no other channel was tried. */
    case RateLimited = 'rate_limited';

    /** No channel outside the centre accepted it. */
    case Failed = 'failed';
}
