<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\RemoteNotification;

/**
 * Takes in a notification another site of a network sent, once its signature is verified: the centre of a network.
 */
interface RemoteNotificationReceiver
{
    public function receive(RemoteNotification $notification): void;
}
