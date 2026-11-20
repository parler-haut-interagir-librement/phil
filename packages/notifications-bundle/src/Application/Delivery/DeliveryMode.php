<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * How a notification type reaches each recipient outside the centre.
 *
 * - `fan_out` hands it to every channel of the type the recipient did not turn off, through Symfony Notifier and the
 *   Web Push sender, as Notifier itself does.
 * - `first_success` tries those channels one after the other, in the order of the type's list, synchronously, and
 *   stops at the first one whose provider accepts it.
 *
 * The centre, its toast and the broadcast channels are served the same way in both modes.
 */
enum DeliveryMode: string
{
    case FanOut = 'fan_out';

    case FirstSuccess = 'first_success';
}
