<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture;

use Override;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * A pool that counts its commits and answers them as told; a sub-namespace of it answers the same.
 */
final class RecordingPool implements AdapterInterface, NamespacedPoolInterface
{
    public int $commits = 0;

    public function __construct(
        private readonly bool $commitSucceeds = true,
        private ArrayAdapter $items = new ArrayAdapter(),
    ) {
    }

    #[Override]
    public function getItem(mixed $key): CacheItem
    {
        return $this->items->getItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItem>
     */
    #[Override]
    public function getItems(array $keys = []): iterable
    {
        return $this->items->getItems($keys);
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return $this->items->hasItem($key);
    }

    #[Override]
    public function clear(string $prefix = ''): bool
    {
        return $this->items->clear($prefix);
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        return $this->items->deleteItem($key);
    }

    /**
     * @param array<string> $keys
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        return $this->items->deleteItems($keys);
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        return $this->items->save($item);
    }

    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->items->saveDeferred($item);
    }

    #[Override]
    public function commit(): bool
    {
        ++$this->commits;

        return $this->commitSucceeds;
    }

    #[Override]
    public function withSubNamespace(string $namespace): static
    {
        $clone = clone $this;
        $clone->items = $this->items->withSubNamespace($namespace);

        return $clone;
    }
}
