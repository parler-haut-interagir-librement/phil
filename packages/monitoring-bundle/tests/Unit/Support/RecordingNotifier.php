<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A notifier that records what it sends, and to whom.
 */
final class RecordingNotifier implements NotifierInterface
{
    /**
     * @var list<array{Notification, list<RecipientInterface>}>
     */
    public array $sent = [];

    #[Override]
    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        $this->sent[] = [$notification, array_values($recipients)];
    }

    public function count(): int
    {
        return \count($this->sent);
    }
}
