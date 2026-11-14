<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Phil\NotificationsBundle\Domain\ValueObject\NotificationData;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

/**
 * One notification of one recipient, made of references only: its type, who it is for, who caused it, what it is about
 * and a few more identifiers. What it says is rendered from its type's templates when it is shown.
 *
 * Similar notifications collapse into one: a later one of the same type about the same subject, while the first is
 * still unread, counts one more and names the latest actor, instead of adding a line.
 */
final class Notification
{
    public readonly string $id;

    public readonly string $type;

    public readonly string $recipient;

    public readonly ?string $origin;

    public private(set) ?string $actor;

    public private(set) NotificationData $data;

    public private(set) NotificationState $state = NotificationState::Unread;

    /** How many notifications this one stands for, after collapsing. */
    public private(set) int $count = 1;

    /** When it last changed: its creation, or the last notification collapsed into it. */
    public private(set) DateTimeImmutable $updatedAt;

    public private(set) ?DateTimeImmutable $readAt = null;

    /**
     * @throws InvalidNotification when an identifier or a key is of the wrong shape
     */
    public function __construct(
        string $id,
        string $type,
        string $recipient,
        public readonly ?SubjectReference $subject,
        public readonly DateTimeImmutable $createdAt,
        ?string $actor = null,
        ?NotificationData $data = null,
        ?string $origin = null,
    ) {
        $this->id = Identifiers::id($id, 'notification identifier');
        $this->type = Identifiers::key($type, 'notification type');
        $this->recipient = Identifiers::id($recipient, 'recipient');
        $this->actor = null === $actor ? null : Identifiers::id($actor, 'actor');
        $this->data = $data ?? NotificationData::none();
        $this->origin = null === $origin ? null : Identifiers::key($origin, 'origin');
        $this->updatedAt = $createdAt;
    }

    /**
     * A stored notification, as a repository reads it back.
     *
     * @throws InvalidNotification when a part is of the wrong shape, or the count is below one
     */
    public static function restore(
        string $id,
        string $type,
        string $recipient,
        ?SubjectReference $subject,
        DateTimeImmutable $createdAt,
        ?string $actor,
        NotificationData $data,
        ?string $origin,
        NotificationState $state,
        int $count,
        DateTimeImmutable $updatedAt,
        ?DateTimeImmutable $readAt,
    ): self {
        if ($count < 1) {
            throw InvalidNotification::because('The count of a stored notification is one or more.');
        }

        $notification = new self($id, $type, $recipient, $subject, $createdAt, $actor, $data, $origin);
        $notification->state = $state;
        $notification->count = $count;
        $notification->updatedAt = $updatedAt;
        $notification->readAt = $readAt;

        return $notification;
    }

    /**
     * What similar notifications share: the recipient's type and subject, `type|subject-type:id`.
     */
    public function groupKey(): string
    {
        return $this->type.'|'.($this->subject?->key() ?? '');
    }

    /**
     * Takes a later, similar notification in: one more in the count, its actor, its data and its time.
     */
    public function collapse(self $later): void
    {
        ++$this->count;
        $this->actor = $later->actor;
        $this->data = $later->data;
        $this->updatedAt = $later->createdAt;
    }

    /**
     * @return bool whether it was unread
     */
    public function markRead(DateTimeImmutable $at): bool
    {
        if (NotificationState::Unread !== $this->state) {
            return false;
        }

        $this->state = NotificationState::Read;
        $this->readAt = $at;

        return true;
    }

    /**
     * @return bool whether it was not archived yet
     */
    public function archive(): bool
    {
        if (NotificationState::Archived === $this->state) {
            return false;
        }

        $this->state = NotificationState::Archived;

        return true;
    }
}
