<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;

/**
 * A repository that counts the purges asked of it, and hands everything to another.
 */
final class CountingPurges implements NotificationRepository
{
    public int $purges = 0;

    public function __construct(
        private readonly NotificationRepository $inner,
    ) {
    }

    #[Override]
    public function record(Notification $notification, bool $collapse): Notification
    {
        return $this->inner->record($notification, $collapse);
    }

    #[Override]
    public function find(string $recipient, string $id): ?Notification
    {
        return $this->inner->find($recipient, $id);
    }

    #[Override]
    public function unreadCount(string $recipient): int
    {
        return $this->inner->unreadCount($recipient);
    }

    #[Override]
    public function latest(string $recipient, int $limit): array
    {
        return $this->inner->latest($recipient, $limit);
    }

    #[Override]
    public function markRead(string $recipient, string $id, DateTimeImmutable $at): bool
    {
        return $this->inner->markRead($recipient, $id, $at);
    }

    #[Override]
    public function markAllRead(string $recipient, DateTimeImmutable $at): int
    {
        return $this->inner->markAllRead($recipient, $at);
    }

    #[Override]
    public function archive(string $recipient, string $id): bool
    {
        return $this->inner->archive($recipient, $id);
    }

    #[Override]
    public function undigested(string $recipient, DateTimeImmutable $since, int $limit): array
    {
        return $this->inner->undigested($recipient, $since, $limit);
    }

    #[Override]
    public function markDigested(string $recipient, array $ids, DateTimeImmutable $at): void
    {
        $this->inner->markDigested($recipient, $ids, $at);
    }

    #[Override]
    public function awaitingDigest(DateTimeImmutable $since, ?string $after, int $limit): array
    {
        return $this->inner->awaitingDigest($since, $after, $limit);
    }

    #[Override]
    public function purge(DateTimeImmutable $readBefore, DateTimeImmutable $unreadBefore, int $limit): int
    {
        ++$this->purges;

        return $this->inner->purge($readBefore, $unreadBefore, $limit);
    }
}
