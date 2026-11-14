<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Domain\Status\NotificationState;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The notifications in memory, for tests and demonstrations: they last as long as the process, and are forgotten when
 * the container is reset between requests.
 */
final class InMemoryNotificationRepository implements NotificationRepository, ResetInterface
{
    /** @var array<string, Notification> by identifier */
    private array $notifications = [];

    /** @var array<int, string> the identifiers the digest has sent */
    private array $digested = [];

    #[Override]
    public function record(Notification $notification, bool $collapse): Notification
    {
        if ($collapse) {
            foreach ($this->notifications as $kept) {
                if ($kept->recipient === $notification->recipient && NotificationState::Unread === $kept->state && $kept->groupKey() === $notification->groupKey()) {
                    $kept->collapse($notification);
                    $this->digested = array_diff($this->digested, [$kept->id]);

                    return $kept;
                }
            }
        }

        $this->notifications[$notification->id] = $notification;

        return $notification;
    }

    #[Override]
    public function find(string $recipient, string $id): ?Notification
    {
        $notification = $this->notifications[$id] ?? null;

        return $notification?->recipient === $recipient ? $notification : null;
    }

    #[Override]
    public function unreadCount(string $recipient): int
    {
        return \count($this->select(static fn (Notification $n): bool => $n->recipient === $recipient && NotificationState::Unread === $n->state));
    }

    #[Override]
    public function latest(string $recipient, int $limit): array
    {
        $listed = $this->select(static fn (Notification $n): bool => $n->recipient === $recipient && NotificationState::Archived !== $n->state);
        usort($listed, static fn (Notification $a, Notification $b): int => [$b->updatedAt, $b->id] <=> [$a->updatedAt, $a->id]);

        return \array_slice($listed, 0, $limit);
    }

    #[Override]
    public function markRead(string $recipient, string $id, DateTimeImmutable $at): bool
    {
        return $this->find($recipient, $id)?->markRead($at) ?? false;
    }

    #[Override]
    public function markAllRead(string $recipient, DateTimeImmutable $at): int
    {
        $marked = 0;

        foreach ($this->select(static fn (Notification $n): bool => $n->recipient === $recipient) as $notification) {
            if ($notification->markRead($at)) {
                ++$marked;
            }
        }

        return $marked;
    }

    #[Override]
    public function archive(string $recipient, string $id): bool
    {
        return $this->find($recipient, $id)?->archive() ?? false;
    }

    #[Override]
    public function undigested(string $recipient, DateTimeImmutable $since, int $limit): array
    {
        $pending = $this->select(fn (Notification $n): bool => $n->recipient === $recipient && $this->awaits($n, $since));
        usort($pending, static fn (Notification $a, Notification $b): int => [$a->updatedAt, $a->id] <=> [$b->updatedAt, $b->id]);

        return \array_slice($pending, 0, $limit);
    }

    #[Override]
    public function markDigested(string $recipient, array $ids, DateTimeImmutable $at): void
    {
        foreach ($ids as $id) {
            if ($this->find($recipient, $id) instanceof Notification) {
                $this->digested[] = $id;
            }
        }
    }

    #[Override]
    public function awaitingDigest(DateTimeImmutable $since, ?string $after, int $limit): array
    {
        $recipients = [];

        foreach ($this->select(fn (Notification $n): bool => $this->awaits($n, $since) && (null === $after || strcmp($n->recipient, $after) > 0)) as $notification) {
            $recipients[] = $notification->recipient;
        }

        $recipients = array_unique($recipients);
        sort($recipients, \SORT_STRING);

        return \array_slice($recipients, 0, $limit);
    }

    #[Override]
    public function purge(DateTimeImmutable $readBefore, DateTimeImmutable $unreadBefore, int $limit): int
    {
        $expired = $this->select(static fn (Notification $n): bool => $n->updatedAt < (NotificationState::Unread === $n->state ? $unreadBefore : $readBefore));

        foreach (\array_slice($expired, 0, $limit) as $notification) {
            unset($this->notifications[$notification->id]);
        }

        return min(\count($expired), $limit);
    }

    #[Override]
    public function reset(): void
    {
        $this->notifications = [];
        $this->digested = [];
    }

    private function awaits(Notification $notification, DateTimeImmutable $since): bool
    {
        return NotificationState::Unread === $notification->state && !\in_array($notification->id, $this->digested, true) && $notification->updatedAt >= $since;
    }

    /**
     * @param callable(Notification): bool $filter
     *
     * @return array<string, Notification>
     */
    private function select(callable $filter): array
    {
        return array_filter($this->notifications, $filter);
    }
}
