<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Support;

use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * Locks with a lease that expires on the clock given, as a shared store does: an expired lease is free for another
 * owner, and its first owner can no longer put it off. `failing` makes every call fail, as a store out of reach.
 */
final class ExpiringLockStore implements PersistingStoreInterface
{
    /** The lease a lock gets when it is saved, before its lifetime is set. */
    private const float INITIAL = 300.0;

    public bool $failing = false;

    /**
     * @var array<string, array{owner: string, until: float}>
     */
    private array $leases = [];

    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function save(Key $key): void
    {
        $this->fail();
        $lease = $this->leases[(string) $key] ?? null;

        if (null !== $lease && $lease['owner'] !== $this->owner($key) && $lease['until'] > $this->now()) {
            throw new LockConflictedException();
        }

        $this->leases[(string) $key] = ['owner' => $this->owner($key), 'until' => $this->now() + self::INITIAL];
    }

    #[Override]
    public function delete(Key $key): void
    {
        $this->fail();

        if ($this->exists($key)) {
            unset($this->leases[(string) $key]);
        }
    }

    #[Override]
    public function exists(Key $key): bool
    {
        $this->fail();
        $lease = $this->leases[(string) $key] ?? null;

        return null !== $lease && $lease['owner'] === $this->owner($key) && $lease['until'] > $this->now();
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        if (!$this->exists($key)) {
            throw new LockConflictedException();
        }

        $this->leases[(string) $key]['until'] = $this->now() + $ttl;
    }

    private function owner(Key $key): string
    {
        if (!$key->hasState(self::class)) {
            $key->setState(self::class, bin2hex(random_bytes(8)));
        }

        $owner = $key->getState(self::class);

        return \is_string($owner) ? $owner : throw new RuntimeException('The owner of a key is a string.');
    }

    private function now(): float
    {
        return (float) $this->clock->now()->format('U.u');
    }

    private function fail(): void
    {
        if ($this->failing) {
            throw new RuntimeException('The lock store is out of reach.');
        }
    }
}
