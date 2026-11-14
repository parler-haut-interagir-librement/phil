<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Override;
use Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver;

/**
 * Delivers a notification another site sent as if this site had sent it, under its origin: its type must be declared
 * here too, with the channels this site gives it.
 */
final readonly class ForwardingRemoteReceiver implements RemoteNotificationReceiver
{
    public function __construct(
        private Notifications $notifications,
    ) {
    }

    #[Override]
    public function receive(RemoteNotification $notification): void
    {
        $this->notifications->send($notification->type, $notification->recipients, $notification->subject, $notification->data->values, $notification->actor, $notification->origin);
    }
}
