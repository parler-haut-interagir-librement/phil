<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\NotificationsBundle\Domain\Model\Notification;

/**
 * Where the notifications of the site channel are kept: each recipient's centre, its unread count, what the digest
 * still has to send, and the retention purge. Every read and change of a centre names its recipient, so that one
 * recipient never reaches another's notifications.
 */
interface NotificationRepository
{
    /**
     * Keeps a new notification. With `$collapse`, a still unread notification of the same group takes it in instead —
     * one more in its count, the later actor and data — and comes back to the digest.
     *
     * @return Notification the notification kept: the new one, or the one it collapsed into
     */
    public function record(Notification $notification, bool $collapse): Notification;

    public function find(string $recipient, string $id): ?Notification;

    public function unreadCount(string $recipient): int;

    /**
     * The unread and read notifications of a recipient, the latest change first.
     *
     * @param positive-int $limit
     *
     * @return list<Notification>
     */
    public function latest(string $recipient, int $limit): array;

    /**
     * @return bool whether the notification was there and unread
     */
    public function markRead(string $recipient, string $id, DateTimeImmutable $at): bool;

    /**
     * @return int how many were unread
     */
    public function markAllRead(string $recipient, DateTimeImmutable $at): int;

    /**
     * @return bool whether the notification was there and not archived yet
     */
    public function archive(string $recipient, string $id): bool;

    /**
     * The unread notifications of a recipient the digest has not sent yet, changed since a moment, oldest first.
     *
     * @param positive-int $limit
     *
     * @return list<Notification>
     */
    public function undigested(string $recipient, DateTimeImmutable $since, int $limit): array;

    /**
     * @param list<string> $ids
     */
    public function markDigested(string $recipient, array $ids, DateTimeImmutable $at): void;

    /**
     * The recipients with an unread notification the digest has not sent, changed since a moment: in the order of
     * their identifiers, after `$after`, one page at a time.
     *
     * @param positive-int $limit
     *
     * @return list<string>
     */
    public function awaitingDigest(DateTimeImmutable $since, ?string $after, int $limit): array;

    /**
     * Deletes up to `$limit` notifications: the read and archived ones last changed before `$readBefore`, the unread
     * ones last changed before `$unreadBefore`.
     *
     * @param positive-int $limit
     *
     * @return int how many were deleted
     */
    public function purge(DateTimeImmutable $readBefore, DateTimeImmutable $unreadBefore, int $limit): int;
}
