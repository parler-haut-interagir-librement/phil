<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Handler;

use Phil\NotificationsBundle\Application\Retention;

/**
 * Handles `PurgeNotifications`, the purge the schedule dispatches: the message carries nothing, its handler is
 * registered for it by the bundle.
 */
final readonly class PurgeNotificationsHandler
{
    public function __construct(
        private Retention $retention,
    ) {
    }

    public function __invoke(): void
    {
        $this->retention->purge();
    }
}
