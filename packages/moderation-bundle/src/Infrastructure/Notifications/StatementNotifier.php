<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Infrastructure\Notifications;

use Phil\ModerationBundle\Application\Event\StatementIssued;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * Tells the member a statement of reasons is addressed to them, through `phil/notifications-bundle`, by the type the
 * application declared: the notification carries the identifier of the statement, never its content. A member
 * identifier the notifications refuse gets no notification; the statement stays on the member's page.
 */
final readonly class StatementNotifier
{
    public function __construct(
        private Notifications $notifications,
        private string $type,
    ) {
    }

    public function __invoke(StatementIssued $issued): void
    {
        try {
            $this->notifications->send($this->type, $issued->statement->recipient->value, new SubjectReference(StatementSubjects::SUBJECT, $issued->statement->id));
        } catch (InvalidNotification) {
            // The statement stays on the member's page.
        }
    }
}
