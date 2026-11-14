<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\WebPushSender;
use Phil\NotificationsBundle\Application\RenderedNotification;

final class RecordingWebPush implements WebPushSender
{
    /** @var list<array{string, RenderedNotification}> */
    public array $sent = [];

    #[Override]
    public function send(string $recipient, RenderedNotification $notification): void
    {
        $this->sent[] = [$recipient, $notification];
    }
}
