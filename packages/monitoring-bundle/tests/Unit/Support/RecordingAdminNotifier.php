<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Symfony\Component\Notifier\AdminRecipientsProviderInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A notifier with admin recipients, recording what it sends, and to whom.
 */
final class RecordingAdminNotifier implements NotifierInterface, AdminRecipientsProviderInterface
{
    /**
     * @var list<array{Notification, list<RecipientInterface>}>
     */
    public array $sent = [];

    /**
     * @param list<Recipient> $admins
     */
    public function __construct(
        private readonly array $admins,
    ) {
    }

    #[Override]
    public function send(Notification $notification, RecipientInterface ...$recipients): void
    {
        $this->sent[] = [$notification, array_values($recipients)];
    }

    #[Override]
    public function getAdminRecipients(): array
    {
        return $this->admins;
    }
}
