# Extension points

The application implements the reachable tenants and marks its entities; it may replace the store and add
activations.

| Extension point | Kind | Implemented by | Wiring |
|---|---|---|---|
| `Application\Contract\ReachableTenantsProvider` | port | the application | its service id in `phil_tenancy.reachable_tenants` |
| `Application\Contract\ActiveTenantStore` | port | the bundle (`SessionActiveTenantStore`), or the application | `phil_tenancy.store`: `session`, or the adapter's service id |
| `Application\Contract\TenantActivation` | port | the bundle (`DoctrineTenantActivation`), and the application for anything else that follows the tenant | autoconfiguration, or the tag `phil_tenancy.activation` |
| `Application\Contract\HostTenants` | port | the bundle (`ConfiguredHostTenants`), or the application | `phil_tenancy.hosts`, or an alias of the port to the adapter |
| `Application\Contract\HostAliases` | port | the bundle (`ConfiguredHostTenants`), or the application | `phil_tenancy.host_aliases`, or an alias of the port to the adapter |
| `Application\Contract\AccountSharing` | port | the bundle (`ConfiguredAccountSharing`), or the application | `phil_tenancy.authentication.account_sharing` |
| `Application\Contract\TenantPlacement` | port | a database bundle, or the application | the bundle that moves the connection reads it; the bundle itself never does |
| `Infrastructure\Mailer\Contract\TenantMailSettings` | port | the bundle (`ConfiguredTenantMailSettings`), or the application | `phil_tenancy.mailer.settings` |
| `Application\Event\HostTenantResolved` | event | the bundle dispatches it | a listener of the application or of a commerce bundle |
| `Domain\Attribute\TenantScoped` | class attribute | each tenant-owned root entity | none; read by `ActiveTenantFilter` |
| `Infrastructure\Doctrine\ActiveTenantFilter` | ORM filter | the bundle | registered enabled by the bundle (`doctrine_filter`), or by the application under `ActiveTenantFilter::NAME` |

`TenantActivation` is described in [integrations.md](integrations.md), `HostTenants` and the shop network in
[hosts-and-shops.md](hosts-and-shops.md), `AccountSharing` in [authentication.md](authentication.md), and
`TenantMailSettings` in [mail.md](mail.md).

## `HostTenants`

| Method | Contract |
|---|---|
| `tenantFor(string $host): ?TenantId` | The tenant the host serves, or `null`; the host comes in lowercase and in its ASCII form |

Invariants: called once per main request, before the firewall — keep it cheap, from memory or a cache; never a
tenant for a host the application does not serve. Wire an adapter by aliasing the port, with `hosts` left empty
and the listener registered as the bundle registers it:

```php
$services->alias(HostTenants::class, StoredHostTenants::class);
$services->set(ResolveHostTenant::class)
    ->args([service(HostTenants::class), service(TenantContext::class), service('event_dispatcher')])
    ->tag('kernel.event_listener', ['event' => 'kernel.request', 'before' => ['security.firewall', 'debug.security.firewall', ResolveActiveTenant::class]]);
```

## `HostAliases`

| Method | Contract |
|---|---|
| `canonicalHostOf(string $host): ?string` | The canonical host the alias stands for, or `null` for a host that is no alias; both in lowercase and in their ASCII form |

Invariants: called once per main request, before `ResolveHostTenant` — keep it cheap; the canonical host is one of the
application's hosts, never a value taken from the request. The adapter's `HostTenants` answers an alias with the tenant
of its canonical host. Wire it as `HostTenants` is wired:

```php
$services->alias(HostAliases::class, StoredHostTenants::class);
$services->set(RedirectHostAlias::class)
    ->args([service(HostAliases::class)])
    ->tag('kernel.event_listener', ['event' => 'kernel.request', 'before' => [ResolveHostTenant::class, 'security.firewall', 'debug.security.firewall']]);
```

## `TenantPlacement`

Where each tenant's data lives: in the shared database, kept apart from the other tenants by the Doctrine filter, or
in a database of its own. The bundle declares the question and the answer only; a bundle that moves the Doctrine
connection with the active tenant, or the application, implements it.

| Method | Contract |
|---|---|
| `placementOf(TenantId $tenant): Placement` | `Placement::shared()`, or `Placement::dedicated('tenant_retail')` |

Invariants:

- **Asked on each entry into a tenant**: keep it cheap — from memory, a cache, or one indexed query on a connection
  that does not follow the tenant itself.
- **A tenant the application does not know is shared**, where the filter shows it nothing.
- **A placement names a database, never how to reach it.** `Placement::dedicated()` takes lowercase ASCII letters,
  digits and underscores, starting with a letter, at most 63 bytes: a name that is the same on every platform and can
  never be read as SQL. The server and the account of that database are the business of whoever moves the connection.

`Placement::equals()` compares the databases, and `toString()` gives `shared` or the database's name, for a log line.

## `ActiveTenantStore`

Where the application remembers the tenant the account chose.

| Method | Contract |
|---|---|
| `remembered(): ?TenantId` | The remembered choice, or `null` when there is none |
| `remember(TenantId $tenant): void` | Remember this choice, replacing any previous one |
| `forget(): void` | Forget the choice; a later `remembered()` returns `null` |

Invariants:

- **The semantics are the application's**: sticky per browser tab, per session, carried in the URL. None of them can
  be decided honestly for every application.
- **Never in the identity provider's session, an ID token or a provider cookie**, which span every application
  while an active tenant belongs to one.
- `remember()` receives only tenants the rule has already checked; the store does not check again.

The bundle's `SessionActiveTenantStore` keeps the choice in the application's own Symfony session — never the
identity provider's — under `_phil_tenancy.active_tenant`: one choice per browser, as long as the session. A
request without a session cookie remembers nothing and starts no session; a stored value that is no longer a valid
reference is dropped. Write another store for another semantics — one choice per browser tab, carried in the URL
— and name it in `phil_tenancy.store`:

```php
final readonly class UrlActiveTenantStore implements ActiveTenantStore
{
    public function __construct(private RequestStack $requests) {}

    public function remembered(): ?TenantId
    {
        $value = $this->requests->getMainRequest()?->attributes->get('tenant');

        return \is_string($value) ? TenantId::fromString($value) : null;
    }

    public function remember(TenantId $tenant): void {} // the next URL carries it

    public function forget(): void {}
}
```

## `ReachableTenantsProvider`

The tenants the current account can act in.

| Method | Contract |
|---|---|
| `reachable(): ReachableTenants` | The set read from the entitlements the application already holds |

Invariants:

- **Fail closed.** Entitlements that can no longer be served (too stale, unreadable) resolve to
  `ReachableTenants::none()`, never to the last set seen.
- **No call to the identity provider on the request path for a switch.** Read the cached entitlements; that is what
  keeps a switch instant and working while the provider is down.
- **No duplicates.** `ReachableTenants::of()` refuses them; a duplicate means the entitlements were read wrong.

```php
namespace App\Tenancy;

use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

final readonly class EntitlementsReachableTenants implements ReachableTenantsProvider
{
    public function __construct(private EntitlementCache $entitlements) // the application's own cache
    {
    }

    public function reachable(): ReachableTenants
    {
        $nodes = $this->entitlements->forCurrentAccount(); // null once it can no longer be served

        if (null === $nodes) {
            return ReachableTenants::none();
        }

        return ReachableTenants::of(...array_map(
            static fn (int|string $reference): TenantId => TenantId::fromString((string) $reference),
            array_keys($nodes),
        ));
    }
}
```

## `#[TenantScoped]`

- Contract: `new TenantScoped(string $field = 'tenant')`, on the class; the field is a mapped field or a
  single-column association whose column holds the tenant reference.
- Invariants: on the root of an inheritance hierarchy; never on a subclass alone.
- See [doctrine-filter.md](doctrine-filter.md).

## Testing doubles

The bundle's own tests use two doubles that are a good starting point for an application's tests: an in-memory
store that counts `forget()` calls, and a provider that returns a fixed set. See [testing.md](testing.md).
