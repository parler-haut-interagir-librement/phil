<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * A lock factory that keeps each lock it creates, so that a test sees the state of the lock itself: a lock that goes
 * out of scope releases on its own, which hides whether its owner released it.
 */
final class KeepingLockFactory extends LockFactory
{
    /**
     * @var list<SharedLockInterface>
     */
    public array $created = [];

    #[Override]
    public function createLock(string $resource, ?float $ttl = 300.0, bool $autoRelease = true): SharedLockInterface
    {
        return $this->created[] = parent::createLock($resource, $ttl, $autoRelease);
    }

    /**
     * The lock created last.
     */
    public function last(): SharedLockInterface
    {
        return $this->created[array_key_last($this->created) ?? throw new LogicException('No lock was created.')];
    }
}
