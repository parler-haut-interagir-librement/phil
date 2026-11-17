<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A lock store in memory that keeps the lifetimes it is asked for, and how many locks it released.
 */
final class RecordingLockStore implements PersistingStoreInterface
{
    /** @var list<float> */
    public array $ttls = [];

    public int $deleted = 0;

    private readonly InMemoryStore $inner;

    public function __construct()
    {
        $this->inner = new InMemoryStore();
    }

    #[Override]
    public function save(Key $key): void
    {
        $this->inner->save($key);
    }

    #[Override]
    public function delete(Key $key): void
    {
        ++$this->deleted;
        $this->inner->delete($key);
    }

    #[Override]
    public function exists(Key $key): bool
    {
        return $this->inner->exists($key);
    }

    #[Override]
    public function putOffExpiration(Key $key, float $ttl): void
    {
        $this->ttls[] = $ttl;
        $this->inner->putOffExpiration($key, $ttl);
    }
}
