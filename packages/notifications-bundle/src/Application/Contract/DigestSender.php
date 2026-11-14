<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Sends one recipient their digest: the unread notifications the last one did not hold, written out.
 */
interface DigestSender
{
    /**
     * @param non-empty-list<RenderedNotification> $notifications
     */
    public function send(RecipientInterface $contact, DigestFrequency $frequency, array $notifications, ?string $locale): void;
}
