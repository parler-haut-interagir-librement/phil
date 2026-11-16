<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A cache pool that keeps its first saves, then answers `false` to every other one, as PSR-6 lets a pool that could not
 * persist an item do.
 */
final class RefusingPool implements CacheItemPoolInterface
{
    public function __construct(
        private int $accepted = 0,
        private readonly ArrayAdapter $inner = new ArrayAdapter(),
    ) {
    }

    #[Override]
    public function getItem(string $key): CacheItemInterface
    {
        return $this->inner->getItem($key);
    }

    /**
     * @return iterable<string, CacheItemInterface>
     */
    #[Override]
    public function getItems(array $keys = []): iterable
    {
        return $this->inner->getItems($keys);
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return $this->inner->hasItem($key);
    }

    #[Override]
    public function clear(): bool
    {
        return $this->inner->clear();
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        return $this->inner->deleteItem($key);
    }

    #[Override]
    public function deleteItems(array $keys): bool
    {
        return $this->inner->deleteItems($keys);
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        if ($this->accepted <= 0) {
            return false;
        }

        --$this->accepted;

        return $this->inner->save($item);
    }

    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    #[Override]
    public function commit(): bool
    {
        return true;
    }
}
