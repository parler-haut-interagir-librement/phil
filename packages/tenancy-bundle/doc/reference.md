# Reference

Every public type of the bundle, by layer.

## `Phil\TenancyBundle\Domain`

| Type | Kind | Role | State |
|---|---|---|---|
| `ValueObject\TenantId` | `final readonly class`, `Stringable` | Opaque tenant reference scoped to one application; byte-for-byte equality; at most 255 bytes | Implemented and tested |
| `ValueObject\ReachableTenants` | `final readonly class`, `Countable`, `IteratorAggregate` | The tenants an account can act in; duplicates refused; may be empty | Implemented and tested |
| `Resolution\ActiveTenantRule` | `final readonly class`, static functions | `resolve()` and `switchTo()` against the reachable set | Implemented and tested |
| `Resolution\Resolution` | `final readonly class` | Outcome, reachable set, `staleChoiceDropped`, `tenant()`, `tenantOrNull()` | Implemented and tested |
| `Resolution\Outcome` | string-backed enum | `Active`, `SelectionRequired`, `NoneReachable` | Implemented and tested |
| `Attribute\TenantScoped` | class attribute | Marks a tenant-owned entity and names its tenant field (default `tenant`) | Implemented and tested |
| `Exception\TenantNotReachable` | `DomainException`, `DomainError` | A switch to a tenant outside the reachable set | Implemented and tested |
| `Exception\NoActiveTenant` | `DomainException`, `DomainError` | The tenant was asked for when none is active | Implemented and tested |
| `Shop\ShopNetwork` | `final readonly class` | The shops, the catalogues each one includes, one level deep; shared customer accounts | Implemented and tested |
| `Shop\OrderOrigin` | `final readonly class` | The shop an order was placed in and the catalogues of its lines | Implemented and tested |
| `Placement\Placement` | `final readonly class` | Where a tenant's data lives: `shared()`, or `dedicated()` in a named database; the name checked | Implemented and tested |

## `Phil\TenancyBundle\Application`

| Type | Kind | Role | State |
|---|---|---|---|
| `ActiveTenant` | `final readonly class` | `resolve()`, `switchTo()`, `leave()` over the two ports | Implemented and tested; registered once `reachable_tenants` is set |
| `TenantContext` | `final class` | The tenant of the current request, message or command; `enter()`, `leave()`, `within()`, `require()` | Implemented and tested; registered |
| `Contract\ActiveTenantStore` | port | Where the chosen tenant is remembered | `SessionActiveTenantStore` in the bundle, or the application's |
| `Contract\ReachableTenantsProvider` | port | Where the reachable set comes from; fails closed | The application implements it |
| `Contract\TenantActivation` | port | What follows the active tenant | `DoctrineTenantActivation` in the bundle, and the application's |
| `Contract\HostTenants` | port | The tenant a host serves | `ConfiguredHostTenants` in the bundle, or the application's |
| `Contract\HostAliases` | port | The canonical host an alias stands for | `ConfiguredHostTenants` in the bundle, or the application's |
| `Contract\AccountSharing` | port | Whether an account signed in on one tenant is signed in on another | `ConfiguredAccountSharing` in the bundle, or the application's |
| `Contract\TenantPlacement` | port | Whether a tenant lives in the shared database or in one of its own | A database bundle, or the application; the bundle registers no implementation |
| `Shop\ShopContext` | `final readonly class` | The shop of the request: its catalogues, what it sells, the origin of an order | Implemented and tested; registered |
| `Event\HostTenantResolved` | `final readonly class` | The host and the tenant entered for it | Implemented and tested |

## `Phil\TenancyBundle\Infrastructure`

| Type | Kind | Role | State |
|---|---|---|---|
| `Doctrine\ActiveTenantFilter` | `final class`, ORM `SQLFilter` | Restricts `#[TenantScoped]` entities to the active tenant; fails closed | Implemented and tested; registered enabled by the bundle |
| `Exception\MisconfiguredTenantScope` | `final class`, `LogicException` | A scope the filter cannot apply | Implemented and tested |
| `Doctrine\DoctrineTenantActivation` | `final readonly class`, `TenantActivation` | Sets the context's tenant on every enabled filter; detaches the tenant-scoped entities of the identity map when the tenant changes; a fresh filter on leaving | Implemented and tested |
| `Doctrine\TenantColumn` | `final class` | `of()`: the tenant column of an entity, as the filter and the policies read it; `field()`: the field or association that holds it | Implemented and tested |
| `Doctrine\EntityTenant` | `final class` | `of()`: the tenant reference an entity holds, through a field or the identifier of an associated tenant entity | Implemented and tested |
| `Doctrine\TenantWriteGuard` | `final class`, `onFlush` listener | Refuses a flush that writes a tenant-scoped entity of another tenant than the active one | Implemented and tested; registered with `doctrine_filter` |
| `Exception\CrossTenantWrite` | `final class`, `RuntimeException` | The flush the write guard refused | Implemented and tested |
| `Doctrine\RowLevelSecurity\RowLevelSecurityConfig` | `final readonly class` | The session variable and the policy name, checked | Implemented and tested |
| `Doctrine\RowLevelSecurity\TenantSession` | `final class`, `TenantActivation` | Sets the active tenant on every open PostgreSQL connection; empties it on leaving | Implemented and tested on PostgreSQL |
| `Doctrine\RowLevelSecurity\TenantSessionMiddleware`, `TenantSessionDriver` | DBAL middleware and driver | Hand each new connection to `TenantSession` | Implemented and tested on PostgreSQL |
| `Doctrine\RowLevelSecurity\RowLevelSecurityPolicies`, `TenantTable` | `final readonly class` | The tenant-scoped tables and the statements that enable or disable their policy | Implemented and tested on PostgreSQL |
| `Console\RowLevelSecurityPoliciesCommand` | console command | `phil:tenancy:rls:policies`: the statements, for a migration | Implemented and tested |
| `Console\RowLevelSecurityCheckCommand` | console command | `phil:tenancy:rls:check`: the role, and the security and policy of each table | Implemented and tested on PostgreSQL |
| `Session\SessionActiveTenantStore` | `final readonly class`, `ActiveTenantStore` | The choice in the application's own session | Implemented and tested |
| `Http\ResolveActiveTenant` | `final readonly class`, request listener | Resolves and enters the tenant; redirects to the selector | Implemented and tested |
| `Http\SwitchActiveTenant` | `final readonly class`, controller | The `phil_tenancy_switch` route; same-origin only | Implemented and tested |
| `Attribute\CurrentTenant` | parameter attribute | Asks for the active tenant as a controller argument | Implemented and tested |
| `Http\CurrentTenantValueResolver` | `final readonly class`, `ValueResolverInterface` | Resolves `#[CurrentTenant]` | Implemented and tested |
| `Http\ConfiguredHostTenants` | `final readonly class`, `HostTenants`, `HostAliases` | The hosts of `phil_tenancy.hosts` and the aliases of `host_aliases`, read through `phil/url` | Implemented and tested |
| `Http\RedirectHostAlias` | `final readonly class`, request listener | Sends an alias to its canonical host: `301`, or `308` for a method other than `GET` and `HEAD` | Implemented and tested |
| `Http\ResolveHostTenant` | `final class`, request listener | Enters the host's tenant before the firewall; warns once per process without trusted hosts | Implemented and tested |
| `Security\TenantScopedUserProvider` | `final readonly class`, `UserProviderInterface`, `PasswordUpgraderInterface` | Refuses a session's account on another tenant than its own | Implemented and tested |
| `Security\RememberSignInTenant` | `final readonly class`, security listener | The tenant of each login, in the session | Implemented and tested |
| `Security\TenantRequestMatcher` | `final readonly class`, `RequestMatcherInterface` | Matches the requests in its tenants, for a firewall of each tenant | Implemented and tested |
| `Security\TenantUserProviders` | `final readonly class`, `UserProviderInterface`, `PasswordUpgraderInterface` | The user provider of the request's tenant, or the default; no account without either | Implemented and tested |
| `Security\ConfiguredAccountSharing` | `final readonly class`, `AccountSharing` | Shared tenant groups and shop links | Implemented and tested |
| `Mailer\TenantTransports` | `final readonly class`, `TransportInterface` | The tenant's transport, sender and reply address on each message | Implemented and tested |
| `Mailer\TenantMail` | `final readonly class` | A tenant's transport, sender and reply address | Implemented and tested |
| `Mailer\Contract\TenantMailSettings` | port | The mail settings of each tenant | `ConfiguredTenantMailSettings` in the bundle, or the application's |
| `Mailer\ConfiguredTenantMailSettings` | `final readonly class`, `TenantMailSettings` | The settings of `phil_tenancy.mailer`, checked | Implemented and tested |
| `Messenger\TenantStamp` | `final readonly class`, `StampInterface` | The reference of the tenant a message was dispatched in, checked | Implemented and tested |
| `Messenger\TenantMiddleware` | `final readonly class`, `MiddlewareInterface` | Stamps on dispatch, handles in the stamp's tenant on receipt | Implemented and tested |
| `Console\TenantOption` | `final readonly class`, console listener | The `--tenant` option of every command | Implemented and tested |
| `Cache\TenantScopedPool` | `final class`, `AdapterInterface`, `CacheInterface`, `NamespacedPoolInterface`, `TenantActivation` | A pool scoped to the active tenant: the tenant's sub-namespace on each call, the pool itself with none | Implemented and tested; decorates `cache.app` and the pools built on it |
| `Cache\TenantScopedTagAwarePool` | `final class`, `TagAwareAdapterInterface`, `TagAwareCacheInterface`, `NamespacedPoolInterface`, `TenantActivation` | Tags prefixed for the active tenant; the sub-namespace too over a natively tag-aware pool | Implemented and tested; decorates `cache.app.taggable` and the tag-aware pools |
| `Cache\TenantCacheNamespace` | `final class`, static functions | The sub-namespace and the tag prefix of a tenant; the reserved names | Implemented and tested |
| `Cache\TenantPools` | `final class` | The pool of each tenant derived through `withSubNamespace()`, kept while in use | Implemented and tested |
| `Cache\TenantTags` | `final class`, static functions | The tags of an item prefixed on saving, stripped on reading | Implemented and tested |
| `Cache\TenantCachePass` | `final readonly class`, compiler pass | Decorates the pools built on `cache.app`, except the shared ones | Implemented and tested |
| `Twig\TenancyExtension` | `final readonly class`, Twig attribute extension | `active_tenant()`, `reachable_tenants()` | Implemented and tested |

## The bundle

| Type | Kind | Role | State |
|---|---|---|---|
| `PhilTenancyBundle` | `final class`, `AbstractBundle` | Extension alias `phil_tenancy`; the configuration of [configuration.md](configuration.md) | Implemented and tested |

## Not in the bundle

| Part | Why |
|---|---|
| A reachable-tenants adapter | Its source — claims, a membership table, an API — is the application's ([extension-points.md](extension-points.md)) |
| The selector page | Its rendering, and any fresh authentication, are the application's ([resolution.md](resolution.md)) |
| Catalogues, carts and orders | A commerce bundle's; it reads the shop network ([hosts-and-shops.md](hosts-and-shops.md)) |
