<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\ToastPublisher;
use Phil\NotificationsBundle\Application\RenderedNotification;

final class RecordingToasts implements ToastPublisher
{
    /** @var list<array{RenderedNotification, int}> */
    public array $published = [];

    #[Override]
    public function publish(RenderedNotification $notification, int $unread): void
    {
        $this->published[] = [$notification, $unread];
    }
}
