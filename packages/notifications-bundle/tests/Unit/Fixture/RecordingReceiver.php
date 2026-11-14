<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver;
use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;
use Phil\NotificationsBundle\Application\RemoteNotification;

/**
 * Keeps what it receives, and refuses the type `unknown` as an undeclared one.
 */
final class RecordingReceiver implements RemoteNotificationReceiver
{
    /** @var list<RemoteNotification> */
    public array $received = [];

    #[Override]
    public function receive(RemoteNotification $notification): void
    {
        if ('unknown' === $notification->type) {
            throw UnknownNotificationType::named('unknown');
        }

        $this->received[] = $notification;
    }
}
