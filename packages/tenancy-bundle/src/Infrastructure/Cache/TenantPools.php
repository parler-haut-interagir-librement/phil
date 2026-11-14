<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Cache;

use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\NamespacedPoolInterface;

/**
 * The pool of each tenant, derived from one root pool through `withSubNamespace()` and kept while it is in use, so
 * that an item saved deferred in a tenant is committed by the same pool. At most {@see self::MAX_TENANTS} tenants
 * are kept: the oldest is committed and dropped to make room. The global pool, when no tenant is active, is the root
 * pool itself, below the sub-namespaces asked of the decorator. Before a pool is derived, the root pool commits what it
 * holds deferred, so that nothing deferred in one namespace is committed in another.
 *
 * Nothing here says which tenant is active: the caller passes it on every call.
 *
 * @template T of AdapterInterface&NamespacedPoolInterface
 */
final class TenantPools
{
    public const int MAX_TENANTS = 32;

    /**
     * @var array<string, T>
     */
    private array $tenants = [];

    /**
     * @var T|null
     */
    private ?AdapterInterface $global = null;

    /**
     * @param T            $root
     * @param list<string> $subNamespaces the sub-namespaces asked of the decorator, below the tenant's
     */
    public function __construct(
        private readonly AdapterInterface&NamespacedPoolInterface $root,
        private readonly array $subNamespaces = [],
    ) {
    }

    /**
     * @return T
     */
    public function root(): AdapterInterface
    {
        return $this->root;
    }

    /**
     * @param list<string> $subNamespaces
     *
     * @return self<T>
     */
    public function below(array $subNamespaces): self
    {
        return new self($this->root, [...$this->subNamespaces, ...$subNamespaces]);
    }

    /**
     * @return T
     */
    public function of(?TenantId $tenant): AdapterInterface
    {
        if (!$tenant instanceof TenantId) {
            return $this->global ??= $this->derive($this->root);
        }

        if (isset($this->tenants[$tenant->value])) {
            return $this->tenants[$tenant->value];
        }

        if (\count($this->tenants) >= self::MAX_TENANTS) {
            $oldest = array_key_first($this->tenants);
            $this->tenants[$oldest]->commit();
            unset($this->tenants[$oldest]);
        }

        return $this->tenants[$tenant->value] = $this->derive($this->root, TenantCacheNamespace::of($tenant));
    }

    /**
     * Commits what every pool kept holds deferred.
     */
    public function commit(): bool
    {
        $ok = true;

        foreach ($this->kept() as $pool) {
            $ok = $pool->commit() && $ok;
        }

        return $ok;
    }

    /**
     * Commits every pool kept, then forgets them; the root pool stays as it is.
     */
    public function forget(): void
    {
        foreach ($this->kept() as $pool) {
            $pool->commit();
        }

        $this->tenants = [];
        $this->global = null;
    }

    /**
     * @return list<T>
     */
    private function kept(): array
    {
        $pools = array_values($this->tenants);

        if ($this->global instanceof AdapterInterface) {
            $pools[] = $this->global;
        }

        return $pools;
    }

    /**
     * The root pool commits first: an adapter derived through `withSubNamespace()` may start with a copy of what its
     * parent holds deferred, and would commit it in its own namespace.
     *
     * @param T $root
     *
     * @return T
     */
    private function derive(AdapterInterface $root, ?string $tenantNamespace = null): AdapterInterface
    {
        $subNamespaces = null === $tenantNamespace ? $this->subNamespaces : [$tenantNamespace, ...$this->subNamespaces];

        if ([] === $subNamespaces) {
            return $root;
        }

        $root->commit();
        $pool = $root;

        foreach ($subNamespaces as $subNamespace) {
            $pool = $pool->withSubNamespace($subNamespace);
        }

        return $pool;
    }
}
