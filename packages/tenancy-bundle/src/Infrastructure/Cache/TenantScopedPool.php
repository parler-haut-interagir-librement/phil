<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * A cache pool scoped to the active tenant: every call reads the tenant of {@see TenantContext} and acts on the
 * pool's sub-namespace of that tenant ({@see TenantCacheNamespace}); with no tenant active, on the pool itself — the
 * global namespace. The tenant is read on each call, never kept from one call to the next, so a worker serving
 * tenants one after another never writes in the namespace of the previous one.
 *
 * `clear()` in a tenant clears that tenant's namespace; with no tenant active, it clears the whole pool, every tenant
 * included, as `cache:pool:clear` does. `prune()` prunes the whole pool. Entering or leaving a tenant, and the reset
 * between requests, commit what is saved deferred and forget the pools of the tenants.
 */
final class TenantScopedPool implements AdapterInterface, CacheInterface, NamespacedPoolInterface, PruneableInterface, ResettableInterface, TenantActivation
{
    /**
     * @var TenantPools<AdapterInterface&CacheInterface&NamespacedPoolInterface>
     */
    private TenantPools $pools;

    public function __construct(
        AdapterInterface&CacheInterface&NamespacedPoolInterface $inner,
        private readonly TenantContext $context,
    ) {
        $this->pools = new TenantPools($inner);
    }

    #[Override]
    public function getItem(mixed $key): CacheItem
    {
        return $this->pool()->getItem($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return iterable<string, CacheItem>
     */
    #[Override]
    public function getItems(array $keys = []): iterable
    {
        return $this->pool()->getItems($keys);
    }

    #[Override]
    public function hasItem(string $key): bool
    {
        return $this->pool()->hasItem($key);
    }

    #[Override]
    public function clear(string $prefix = ''): bool
    {
        return $this->pool()->clear($prefix);
    }

    #[Override]
    public function deleteItem(string $key): bool
    {
        return $this->pool()->deleteItem($key);
    }

    /**
     * @param array<string> $keys
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        return $this->pool()->deleteItems($keys);
    }

    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        return $this->pool()->save($item);
    }

    #[Override]
    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool()->saveDeferred($item);
    }

    #[Override]
    public function commit(): bool
    {
        return $this->pools->commit();
    }

    /**
     * @param array<array-key, mixed>|null $metadata
     */
    #[Override]
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        return $this->pool()->get($key, $callback, $beta, $metadata);
    }

    #[Override]
    public function delete(string $key): bool
    {
        return $this->pool()->delete($key);
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
        $clone->pools = $this->pools->below([$namespace]);

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

    private function pool(): AdapterInterface&CacheInterface&NamespacedPoolInterface
    {
        return $this->pools->of($this->context->current());
    }
}
