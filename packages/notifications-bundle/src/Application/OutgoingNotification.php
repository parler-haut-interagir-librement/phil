<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Override;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * A notification written out, as Symfony Notifier sends it. On a chat channel its text is the title, a blank line and
 * the body, since most chat bridges post a message's subject alone: Discord's webhook would drop the body. A bridge that
 * renders the notification itself, as Slack's blocks do, still reads it, with that text as its fallback.
 */
final class OutgoingNotification extends Notification implements ChatNotificationInterface
{
    #[Override]
    public function asChatMessage(RecipientInterface $recipient, ?string $transport = null): ChatMessage
    {
        $body = $this->getContent();
        $message = ChatMessage::fromNotification($this);

        return '' === $body ? $message : $message->subject($this->getSubject()."\n\n".$body);
    }
}
