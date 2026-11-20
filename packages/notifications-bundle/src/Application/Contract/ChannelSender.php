<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Sends a notification on one channel at once, for the `first_success` mode: the provider's answer is known when
 * `send()` returns. A sender catches only the exceptions that say the provider refused the message or could not be
 * reached — the `TransportExceptionInterface` of Symfony Notifier or Mailer —, and returns `Refused`; any other
 * exception is a fault of the application, and goes up.
 *
 * A service of the application implementing this interface is tagged by autoconfiguration, and asked before the
 * bundle's own senders.
 */
interface ChannelSender
{
    /**
     * Whether the sender serves the channel, such as "sms" or "chat/slack".
     */
    public function supports(string $channel): bool;

    /**
     * @param RecipientInterface|null $contact what the recipient directory gives, null when it has nothing
     */
    public function send(string $channel, RenderedNotification $notification, NotificationType $type, ?RecipientInterface $contact): ChannelOutcome;
}
