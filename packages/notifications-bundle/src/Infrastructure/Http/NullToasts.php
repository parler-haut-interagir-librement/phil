<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Override;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\RenderedNotification;

/**
 * Shows no toast: the notification is in the centre only.
 */
final readonly class NullToasts implements ToastPublisher
{
    #[Override]
    public function publish(RenderedNotification $notification, int $unread): void
    {
    }
}
