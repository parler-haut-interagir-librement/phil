<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use RuntimeException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * A store that does what it is told, whatever the keys: `grants` false refuses every lock as taken, `renews` false
 * refuses to put any expiry off, `releases` false fails every release. A lock it holds always exists. The refusals are
 * what a test turns on to see that the code stops at the first one, not at the step after.
 */
final class ScriptedLockStore implements PersistingStoreInterface
{
    public bool $grants = true;

    public bool $renews = true;

    public bool $releases = true;

    #[Override]
    public function save(Key $key): void
    {
        if (!$this->grants) {
            throw new LockConflictedException();
        }
    }

    #[Override]
    public function delete(Key $key): void
    {
        if (!$this->releases) {
            throw new RuntimeException('The store cannot release the lock.');
        }
    }

    #[Override]
    public function exists(Key $key): bool
    {
        return true;
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        if (!$this->renews) {
            throw new LockConflictedException();
        }
    }
}
