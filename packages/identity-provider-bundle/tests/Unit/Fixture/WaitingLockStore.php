<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A lock store in memory that says how each lock was taken: `wait` by a caller ready to wait for it, `try` by one that
 * is not, for the tests.
 */
final class WaitingLockStore implements BlockingStoreInterface
{
    /** @var list<string> */
    public array $acquisitions = [];

    public function __construct(private readonly InMemoryStore $inner = new InMemoryStore())
    {
    }

    #[Override]
    public function waitAndSave(Key $key): void
    {
        $this->acquisitions[] = 'wait';
        $this->inner->save($key);
    }

    #[Override]
    public function save(Key $key): void
    {
        $this->acquisitions[] = 'try';
        $this->inner->save($key);
    }

    #[Override]
    public function delete(Key $key): void
    {
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
        $this->inner->putOffExpiration($key, $ttl);
    }
}
