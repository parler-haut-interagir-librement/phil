<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Infrastructure\Notifications;

use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Notifies an agent through phil/notifications-bundle that a request was assigned to them by someone else. The
 * notification carries the request's conversation only; its label is that of the conversation, which
 * phil/conversation-bundle names when the notification is shown. An agent who takes a request is not notified.
 */
final readonly class NotifyAssignee
{
    public const string TYPE = 'phil_helpdesk.assigned';

    /** The subject of phil/conversation-bundle's notifications: a request is a conversation. */
    public const string SUBJECT = 'phil_conversation.conversation';

    public function __construct(
        private Notifications $notifications,
        private string $type = self::TYPE,
    ) {
    }

    public function __invoke(TicketAssigned $event): void
    {
        if (null === $event->assignee || $event->assignee === $event->by) {
            return;
        }

        $this->notifications->send($this->type, [$event->assignee], new SubjectReference(self::SUBJECT, $event->conversation), [], $event->by);
    }
}
