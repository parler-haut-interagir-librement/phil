<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountSessionRepository;
use RuntimeException;

/**
 * The sessions in memory, whose next save fails once `failNext` is set, for the tests of a close cut short.
 */
final class FailingSessions implements AccountSessionRepository
{
    public bool $failNext = false;

    public function __construct(public readonly InMemoryAccountSessionRepository $inner = new InMemoryAccountSessionRepository())
    {
    }

    #[Override]
    public function get(string $id): ?AccountSession
    {
        return $this->inner->get($id);
    }

    #[Override]
    public function openFor(string $accountId): array
    {
        return $this->inner->openFor($accountId);
    }

    #[Override]
    public function hasAny(string $accountId): bool
    {
        return $this->inner->hasAny($accountId);
    }

    #[Override]
    public function knowsDevice(string $accountId, string $device): bool
    {
        return $this->inner->knowsDevice($accountId, $device);
    }

    #[Override]
    public function save(AccountSession ...$sessions): void
    {
        if ($this->failNext) {
            $this->failNext = false;

            throw new RuntimeException('The store is down.');
        }

        $this->inner->save(...$sessions);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->inner->purge($before);
    }
}
