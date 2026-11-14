<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\RenderedNotification;

/**
 * Shows a notification kept in the centre to its recipient as a toast, with their new unread count.
 */
interface ToastPublisher
{
    public function publish(RenderedNotification $notification, int $unread): void;
}
