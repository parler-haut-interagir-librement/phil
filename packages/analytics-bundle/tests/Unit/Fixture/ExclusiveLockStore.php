<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A lock store of one process where a second holder, which would wait on another worker, is refused at once with
 * `LockConflictedException`: the test then runs it once the first has released the lock.
 */
final readonly class ExclusiveLockStore implements BlockingStoreInterface
{
    private InMemoryStore $locks;

    public function __construct()
    {
        $this->locks = new InMemoryStore();
    }

    /**
     * @throws LockConflictedException when another key holds the resource
     */
    #[Override]
    public function waitAndSave(Key $key): void
    {
        $this->locks->save($key);
    }

    #[Override]
    public function save(Key $key): void
    {
        $this->locks->save($key);
    }

    #[Override]
    public function delete(Key $key): void
    {
        $this->locks->delete($key);
    }

    #[Override]
    public function exists(Key $key): bool
    {
        return $this->locks->exists($key);
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->locks->putOffExpiration($key, $ttl);
    }
}
