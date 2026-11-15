<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The signed-in sessions in memory, for tests; emptied between requests of a worker.
 */
final class InMemoryAccountSessionRepository implements AccountSessionRepository, ResetInterface
{
    use Snapshots;

    /** @var array<string, AccountSession> */
    private array $sessions = [];

    #[Override]
    public function get(string $id): ?AccountSession
    {
        return self::copy($this->sessions[$id] ?? null);
    }

    #[Override]
    public function openFor(string $accountId): array
    {
        $open = array_filter($this->sessions, static fn (AccountSession $session): bool => $session->accountId() === $accountId && !$session->isClosed());
        usort($open, static fn (AccountSession $a, AccountSession $b): int => [$b->lastSeenAt(), $a->id()] <=> [$a->lastSeenAt(), $b->id()]);

        return self::copies($open);
    }

    #[Override]
    public function hasAny(string $accountId): bool
    {
        return array_any($this->sessions, static fn (AccountSession $session): bool => $session->accountId() === $accountId);
    }

    #[Override]
    public function knowsDevice(string $accountId, string $device): bool
    {
        return array_any($this->sessions, static fn (AccountSession $session): bool => $session->accountId() === $accountId && $session->device() === $device);
    }

    #[Override]
    public function save(AccountSession ...$sessions): void
    {
        foreach ($sessions as $session) {
            $this->sessions[$session->id()] = clone $session;
        }
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $count = \count($this->sessions);
        $this->sessions = array_filter($this->sessions, static fn (AccountSession $session): bool => !$session->isClosed() && $session->lastSeenAt() >= $before);

        return $count - \count($this->sessions);
    }

    #[Override]
    public function reset(): void
    {
        $this->sessions = [];
    }
}
