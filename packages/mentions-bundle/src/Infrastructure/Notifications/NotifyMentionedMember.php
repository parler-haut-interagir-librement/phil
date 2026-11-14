<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Notifications;

use Phil\MentionsBundle\Application\Contract\MentionPreferences;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Notifies a member of their mention, unless they chose not to be: the notification type `member.mentioned` by
 * default, about the subject, caused by the author, with the content as `context` when it is not the subject itself.
 * Identifiers only: the subject's label and the author's name are looked up when the notification is shown.
 */
final readonly class NotifyMentionedMember
{
    public const string TYPE = 'member.mentioned';

    public function __construct(
        private Notifications $notifications,
        private MentionPreferences $preferences,
        private string $type = self::TYPE,
    ) {
    }

    public function __invoke(MemberMentioned $event): void
    {
        if (!$this->preferences->notifies($event->recipient)) {
            return;
        }

        $this->notifications->send(
            $this->type,
            $event->recipient,
            new SubjectReference($event->subject->key, $event->subject->id),
            $event->context->equals($event->subject) ? [] : ['context' => $event->context->toString()],
            $event->author->toString(),
        );
    }
}
