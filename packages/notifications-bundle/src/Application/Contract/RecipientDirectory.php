<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Who stands behind an identifier, looked up as a notification is sent, never stored with it: the address a channel of
 * Symfony Notifier reaches them at, and the name a notification shows for an actor.
 */
interface RecipientDirectory
{
    /**
     * The recipient as Symfony Notifier reaches them — an `EmailRecipientInterface` for email, an
     * `SmsRecipientInterface` for SMS —, or null when they cannot be reached.
     */
    public function contact(string $recipient): ?RecipientInterface;

    /**
     * The name to show for a recipient or an actor, or null when there is none to show.
     */
    public function displayName(string $id): ?string;
}
