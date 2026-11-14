<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * A tag-aware cache pool scoped to the active tenant: every tag an item is saved with, and every tag invalidated, is
 * prefixed with the tenant's namespace ({@see TenantCacheNamespace::tagPrefix()}), so that invalidating a tag in one
 * tenant never reaches the items of another, even through the tag versions a tag-aware adapter keeps in memory. With
 * no tenant active, tags are left as they are.
 *
 * Over a tag-aware adapter whose items live in a pool already scoped by {@see TenantScopedPool}, such as
 * `cache.app.taggable` over `cache.app`, the decorator only prefixes the tags. Over a natively tag-aware pool, such as a
 * Redis tag-aware adapter, it also moves every call into the tenant's sub-namespace, as {@see TenantScopedPool} does.
 *
 * The metadata of an item read here names the tags it was saved with, without the prefix.
 *
 * @see TenantScopedPool for how the tenant is read on each call
 */
final class TenantScopedTagAwarePool implements TagAwareAdapterInterface, TagAwareCacheInterface, NamespacedPoolInterface, PruneableInterface, ResettableInterface, TenantActivation
{
    /**
     * @var TenantPools<TagAwareAdapterInterface&TagAwareCacheInterface&NamespacedPoolInterface>
     */
    private TenantPools $pools;

    /**
     * @param bool $namespaced whether the decorator also moves every call into the tenant's sub-namespace, for a pool
     *                         whose items no {@see TenantScopedPool} scopes already
     */
    public function __construct(
        TagAwareAdapterInterface&TagAwareCacheInterface&NamespacedPoolInterface $inner,
        private readonly TenantContext $context,
        private readonly bool $namespaced = false,
    ) {
        $this->pools = new TenantPools($inner);
    }

    #[Override]
    public function getItem(mixed $key): CacheItem
    {
        $tenant = $this->context->current();
        $item = $this->pool($tenant)->getItem($key);
        TenantTags::strip($item, TenantCacheNamespace::tagPrefix($tenant));

        return $item;
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItem>
     */
    #[Override]
    public function getItems(array $keys = []): iterable
    {
        $tenant = $this->context->current();
        $prefix = TenantCacheNamespace::tagPrefix($tenant);
        $items = [];

        foreach ($this->pool($tenant)->getItems($keys) as $key => $item) {
            TenantTags::strip($item, $prefix);
            $items[$key] = $item;
        }

        return $items;
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return $this->pool($this->context->current())->hasItem($key);
    }

    #[Override]
    public function clear(string $prefix = ''): bool
    {
        return $this->pool($this->context->current())->clear($prefix);
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        return $this->pool($this->context->current())->deleteItem($key);
    }

    /**
     * @param array<string> $keys
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        return $this->pool($this->context->current())->deleteItems($keys);
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        $tenant = $this->context->current();
        $this->prefix($item, $tenant);

        return $this->pool($tenant)->save($item);
    }

    /**
     * Over a pool scoped by {@see TenantScopedPool}, the item is saved at once: the tag-aware adapter would commit it
     * later through that pool, in whichever tenant is active by then.
     */
    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$this->namespaced) {
            return $this->save($item);
        }

        $tenant = $this->context->current();
        $this->prefix($item, $tenant);

        return $this->pool($tenant)->saveDeferred($item);
    }

    #[Override]
    public function commit(): bool
    {
        return $this->namespaced ? $this->pools->commit() : $this->pools->root()->commit();
    }

    /**
     * @param array<array-key, mixed>|null $metadata
     */
    #[Override]
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $tenant = $this->context->current();
        $prefix = TenantCacheNamespace::tagPrefix($tenant);
        $tagged = static function (ItemInterface $item, bool &$save) use ($callback, $prefix): mixed {
            $value = $callback($item, $save);

            if ($item instanceof CacheItem) {
                TenantTags::prefix($item, $prefix);
            }

            return $value;
        };

        try {
            return $this->pool($tenant)->get($key, $tagged, $beta, $metadata);
        } finally {
            if (\is_array($metadata)) {
                $metadata = TenantTags::stripMetadata($metadata, $prefix);
            }
        }
    }

    #[Override]
    public function delete(string $key): bool
    {
        return $this->pool($this->context->current())->delete($key);
    }

    /**
     * @param array<string> $tags
     */
    #[Override]
    public function invalidateTags(array $tags): bool
    {
        $tenant = $this->context->current();
        $prefix = TenantCacheNamespace::tagPrefix($tenant);
        $prefixed = [];

        foreach ($tags as $tag) {
            $prefixed[] = $prefix.TenantCacheNamespace::refuseReserved(CacheItem::validateKey($tag), 'tag');
        }

        return $this->pool($tenant)->invalidateTags($prefixed);
    }

    /**
     * A sub-namespace below the tenant's, or below the pool itself with no tenant active. A name of the form of a
     * tenant's namespace is refused.
     */
    #[Override]
    public function withSubNamespace(string $namespace): static
    {
        TenantCacheNamespace::refuseReserved(CacheItem::validateKey($namespace), 'sub-namespace');
        $clone = clone $this;
        $clone->pools = $this->namespaced
            ? $this->pools->below([$namespace])
            : new TenantPools($this->pools->root()->withSubNamespace($namespace));

        return $clone;
    }

    #[Override]
    public function prune(): bool
    {
        $root = $this->pools->root();

        return $root instanceof PruneableInterface && $root->prune();
    }

    #[Override]
    public function reset(): void
    {
        $this->pools->forget();
        $root = $this->pools->root();

        if ($root instanceof ResettableInterface) {
            $root->reset();
        }
    }

    #[Override]
    public function activate(TenantId $tenant): void
    {
        $this->pools->forget();
    }

    #[Override]
    public function deactivate(): void
    {
        $this->pools->forget();
    }

    private function prefix(CacheItemInterface $item, ?TenantId $tenant): void
    {
        if ($item instanceof CacheItem) {
            TenantTags::prefix($item, TenantCacheNamespace::tagPrefix($tenant));
        }
    }

    private function pool(?TenantId $tenant): TagAwareAdapterInterface&TagAwareCacheInterface&NamespacedPoolInterface
    {
        return $this->namespaced ? $this->pools->of($tenant) : $this->pools->root();
    }
}
