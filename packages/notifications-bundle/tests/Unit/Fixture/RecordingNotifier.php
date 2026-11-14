<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

final class RecordingNotifier implements NotifierInterface
{
    /** @var list<array{Notification, list<RecipientInterface>}> */
    public array $sent = [];

    #[Override]
    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        $this->sent[] = [$notification, array_values($recipients)];
    }
}
