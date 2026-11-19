<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Infrastructure\Notifications;

use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Notifies the recipients of a message through phil/notifications-bundle, those who did not mute the conversation nor
 * block the author. The notification carries the conversation and the message identifiers, never the body; the label of
 * the conversation is read when the notification is shown, by `ConversationSubjects`.
 */
final readonly class NotifyParticipants
{
    public const string TYPE = 'phil_conversation.message';

    public const string SUBJECT = 'phil_conversation.conversation';

    public function __construct(
        private Notifications $notifications,
        private string $type = self::TYPE,
    ) {
    }

    public function __invoke(MessageSent $event): void
    {
        if ([] === $event->notified) {
            return;
        }

        $this->notifications->send(
            $this->type,
            $event->notified,
            new SubjectReference(self::SUBJECT, $event->conversation),
            ['message' => $event->message],
            $event->author,
        );
    }
}
