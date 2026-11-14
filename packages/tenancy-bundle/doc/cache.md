# Cache per tenant

`cache.app`, and every cache pool built on it, is scoped to the active tenant: each tenant reads and writes its own
sub-namespace of the pool, and every tag it saves or invalidates is its own. The same key in two tenants holds two
values; invalidating a tag in one tenant never reaches the items of another. With no tenant active, the pool is the
global namespace. The pools every tenant shares, Symfony's own and the ones the application lists, stay as they are.

The scoping is on by default and needs no code: services that receive `cache.app`, `CacheInterface`,
`TagAwareCacheInterface` or a pool of `framework.cache.pools` receive the scoped pool.

```php
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class MenuCache
{
    public function __construct(
        private TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * Computed once per tenant: the key is the same, the namespace is the active tenant's.
     */
    public function menu(callable $compute): array
    {
        return $this->cache->get('menu', static function (ItemInterface $item) use ($compute): array {
            $item->tag('catalogue');

            return $compute();
        });
    }

    /**
     * Invalidates the menu of the active tenant only.
     */
    public function catalogueChanged(): void
    {
        $this->cache->invalidateTags(['catalogue']);
    }
}
```

## Which pools are scoped

| Pool | Scoped | Why |
|---|---|---|
| `cache.app` | yes | Symfony's application cache |
| `cache.app.taggable`, `TagAwareCacheInterface` | yes, its tags | The tag-aware adapter over `cache.app`; its items live in the scoped `cache.app` |
| A pool of `framework.cache.pools` without an adapter of its own | yes | Its definition inherits from `cache.app` |
| A tag-aware pool of `framework.cache.pools` (`tags: true`) | yes, its items and its tags | Symfony registers its items under `.<pool>.inner`, built on `cache.app`, and its tag-aware adapter under `<pool>` |
| A pool on another adapter (`adapter: cache.adapter.redis`, a DSN, a chain) | no | It is not built on `cache.app`; list it nowhere |
| `cache.system` and the pools built on it | no | Validation, serializer and routing metadata are the same for every tenant |
| Symfony's own pools built on `cache.app` | no | The table below |
| A pool of `phil_tenancy.cache.global_pools` | no | The application shares it |

Symfony's own pools built on `cache.app` are shared by every tenant (`TenantCachePass::SHARED_POOLS`):

| Pool | Why it is shared |
|---|---|
| `cache.messenger.restart_workers_signal` | `messenger:stop-workers` stops every worker, whichever tenant it last served |
| `cache.rate_limiter` | A limit counts every request of a client, whichever tenant it reaches |
| `cache.security`, `cache.security.discovery` | The proofs, the ended sessions, the discovery documents and the keys every server must share |
| `security.authenticator.cache.expired_links` | A login link used once is used everywhere |
| `cache.scheduler` | The schedules run once for the application |
| `cache.http_client.pool` | Responses of other services, the same for every tenant |
| `cache.translation.providers` | The translations of the application |
| `cache.mailer.webhook` | The webhooks of the mail providers, received in no tenant |

A pool built on `cache.app` by another bundle is scoped: a pool every tenant must share is listed in
`global_pools`. `bin/console debug:container --tag=cache.pool` lists the pools; a scoped pool shows as
`TenantScopedPool` or `TenantScopedTagAwarePool`.

## Configuration

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    cache:
        enabled: true
        # The pools built on cache.app that every tenant shares, besides Symfony's own.
        global_pools: [app.exchange_rates]
```

| Key | Default | Effect |
|---|---|---|
| `cache.enabled` | `true` | Scopes `cache.app` and the pools built on it, when `symfony/cache` is installed; `false` leaves every pool shared |
| `cache.global_pools` | `[]` | The pools every tenant shares, named as `framework.cache.pools` names them; `cache.app` itself may be listed. A name that is no cache pool stops the compilation |

A pool listed in `global_pools` is shared with its tags: over a shared `cache.app`, `cache.app.taggable` is shared
too.

## Namespaces and tags

| Active tenant | Sub-namespace | Tag `catalogue` becomes |
|---|---|---|
| none | the pool itself | `catalogue` |
| `acme` | `tenant.acme` | `tenant.acme.catalogue` |
| `shop-2_b` | `tenant.shop-2_b` | `tenant.shop-2_b.catalogue` |
| `Acme Shop`, `a.b`, `café`, 65 characters or more | `tenant-` and the hexadecimal SHA-256 of the reference | the sub-namespace, a dot, `catalogue` |

A reference of at most 64 letters, digits, hyphens and underscores stays readable in the backend; any other is
digested, so that a reference with a character a cache key cannot hold, or two references that differ only by a
character the backend would fold, never share a namespace. The two forms never meet: one follows a dot, the other a
hyphen, and a readable reference holds no dot.

The names that start with `tenant.` or `tenant-` belong to the tenants: `withSubNamespace()` refuses them, and so does a
tag saved or invalidated in them, with `Symfony\Component\Cache\Exception\InvalidArgumentException`. Nothing written in
the global namespace can therefore land in a tenant's.

The metadata of an item read through a scoped pool names the tags it was saved with, without the prefix.

## What each operation does

| Operation | In a tenant | With no tenant active |
|---|---|---|
| `get()`, `getItem()`, `save()`, `delete()` | The tenant's sub-namespace | The pool itself |
| `invalidateTags()` | The tenant's tags | The global tags; never a tenant's |
| `clear()` | The tenant's sub-namespace | The whole pool, every tenant included, as `cache:pool:clear` does |
| `prune()` | The whole pool | The whole pool |
| `withSubNamespace('x')` | `x` below the tenant's sub-namespace | `x` below the pool |
| `saveDeferred()` then `commit()` | Committed in the tenant the item was deferred in | Committed in the global namespace |

On an adapter with versioning enabled (`enableVersioning()`), clearing changes the version of the whole pool: a
`clear()` in a tenant then clears every tenant. No adapter of `framework.cache` enables it by default.

## In a worker

The active tenant is read from `TenantContext` on every call, never kept from one call to the next: a worker that
serves `acme`, then `rose`, writes each request's entries in its own tenant, and a message handled in the tenant of
its `TenantStamp` caches in that tenant. Entering or leaving a tenant commits what was saved deferred, in the tenant it
was saved in, and forgets the pools of the tenants; so does the reset between requests (`kernel.reset`). At most 32
tenants' pools are kept at once; the oldest is committed and dropped to make room.

Over a scoped pool, a tag-aware adapter saves a deferred item at once: it would otherwise commit it later through the
scoped pool, in whichever tenant is active by then. A natively tag-aware pool, such as a Redis tag-aware adapter, is
scoped by the tag-aware decorator itself and keeps its deferred items per tenant.

A command run without `--tenant` acts in the global namespace; `cache:pool:clear cache.app` clears every tenant.

## Testing an application's isolation

```php
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Contracts\Cache\CacheInterface;

$context = static::getContainer()->get(TenantContext::class);
$cache = static::getContainer()->get(CacheInterface::class);

$context->within(TenantId::fromString('acme'), static fn () => $cache->get('menu', static fn (): string => 'acme'));

self::assertSame('rose', $context->within(TenantId::fromString('rose'), static fn () => $cache->get('menu', static fn (): string => 'rose')));
```

The bundle's own isolation tests run on two tenants and the global namespace: the same key, the same tag, an
invalidation, a clear, a deferred save across a tenant switch, a shared pool ([testing.md](testing.md)).
