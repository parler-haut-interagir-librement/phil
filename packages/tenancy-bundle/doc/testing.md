# Testing

## How the bundle is tested

The unit tests are in `tests/Unit`. None boots a kernel or opens a database connection.

| Area | What is covered |
|---|---|
| `TenantId` | Accepted forms, the byte limit, every refusal (empty, too long, invalid UTF-8, surrounding whitespace including Unicode spaces, C0, DEL and C1 controls), case-sensitive equality |
| `ReachableTenants` | `none()`, membership, order kept, list from a keyed spread, `single()`, duplicates refused |
| `ActiveTenantRule`, `Resolution`, `NoActiveTenant` | Every row of the resolution table, the switch rule, the exceptions and their messages |
| `ActiveTenant` | A reachable choice kept, a stale choice forgotten, a switch remembered, a refused switch leaving the choice untouched, `leave()` |
| `TenantScoped` | Default field, empty field refused |
| `ActiveTenantFilter` | Unmarked entities left alone; field and association columns; quoting through the connection; `1 = 0` without a tenant; the two mapping errors |
| `TenantContext` | Entering and leaving reach every activation; `require()`; `within()` returns to the tenant before, or to none, even when the work throws |
| `DoctrineTenantActivation` | The tenant set on every enabled filter, disabled filters left alone, a fresh filter on leaving, nothing without Doctrine |
| `SessionActiveTenantStore` | The main request's session, nothing read without a session cookie, a malformed value dropped, no session to remember in |
| `ResolveActiveTenant` | Each outcome, the `target` on safe requests only, the unresolved routes, a request already in a tenant, sub-requests; after a stand-in firewall registered far below the usual place |
| `ConfiguredHostTenants`, `ResolveHostTenant` | Hosts read in lowercase and in their ASCII form, every refused host, a host listed twice; the tenant entered and announced, a host of no tenant, sub-requests; the warning without trusted hosts, once per process |
| `TenantCacheNamespace` | Readable references, digested references and their test vector, two references that look alike, the tag prefix, the reserved names |
| `TenantScopedPool`, `TenantPools` | On two tenants and the global namespace: the same key, the cache contracts, deleting, clearing a tenant and clearing the whole pool, deferred saves committed in their own tenant across a switch, a leave and a reset, nothing deferred in the global namespace landing in a tenant's, sub-namespaces, pruning, the pools kept and the oldest dropped |
| `TenantScopedTagAwarePool`, `TenantTags` | Over a scoped pool and scoping a tag-aware pool: the same tag invalidated in one tenant and kept in the other and in the global namespace, tag versions kept in memory, the contracts, the metadata without the prefix, an item saved again, reserved tags refused, deferred saves, sub-namespaces |
| `TenantCachePass` | `cache.app`, `cache.app.taggable`, the pools built on them, a tag-aware pool of the application, a natively tag-aware `cache.app`; Symfony's shared pools, the global pools and the pools on other adapters left alone; parent loops; a pool that cannot take a sub-namespace, a global pool that is no pool |
| `ShopNetwork`, `OrderOrigin`, `ShopContext` | The catalogues of each shop, what it sells, the shops that include another, shared accounts, every refused network; an order's distinct catalogues, a catalogue the shop does not sell; nothing sold outside a shop |
| `ConfiguredAccountSharing`, `RememberSignInTenant`, `TenantScopedUserProvider` | Groups and shop links, never a request in no tenant, shared sessions; the tenant remembered at login and forgotten at logout; an account refreshed on its tenant, refused on another, on none and with a malformed remembered value, shared; a session opened in no tenant refused on a tenant, accepted with shared sessions or when the reachable tenants decide |
| `ConfiguredTenantMailSettings`, `TenantTransports` | The sender of each kind of tenant, every refused address; the headers added, those the message carries kept, raw messages and messages in no tenant left alone |
| `TenantStamp` | The reference only, a reference that cannot be a tenant refused |
| `SwitchActiveTenant` | A reachable tenant remembered, the targets never followed off the site, unreachable and malformed tenants refused, the same-origin check |
| `CurrentTenantValueResolver`, `TenantMiddleware`, `TenantOption`, `TenancyExtension` | Their rules, one by one |
| `PhilTenancyBundle` | Every service, argument and tag each configuration registers; the filter prepended to DoctrineBundle, or not |

The filter is exercised on hand-built `ClassMetadata` and an entity manager stub whose connection quotes values,
so its unit tests need no database.

The functional tests, in `tests/Functional`, boot a real kernel (`App\TestKernel`: FrameworkBundle with a mock
session and an in-memory Messenger transport, DoctrineBundle on SQLite, and the bundle configured with two adapters
that read request headers). Requests prove each resolution outcome end to end — the rows the ORM returns, the
redirect to the selector, a stale choice forgotten, nothing visible without a tenant, no tenant carried to the
next request —, the switch, `#[CurrentTenant]` — never a request attribute of the same name —, the session store
through a browser that keeps its cookie, a message handled in the tenant it was dispatched in, a command run with
`--tenant`, each configuration key, the filter outside a request, including `suspend()` and `restore()`, and the cache
pools of the application scoped to the tenant — the same key and the same tag in two tenants, a global pool, the
scoping turned off. A
second kernel (`Sites\SitesKernel`: FrameworkBundle, SecurityBundle and the bundle, four shop hosts sharing a session
cookie, a queue for mail) proves the hosts, the shop network, an account signed in on one shop and on the shops linked
to it but on no other — an `access_control` rule included —, and each shop's mail sent its own way by the queue's
worker, in one container kept across requests as a long-running server keeps it. Run them with
`vendor/bin/phpunit packages/tenancy-bundle/tests/Functional`, on another server with
`PHIL_TENANCY_TEST_DATABASE_URL`; installed on its own, where FrameworkBundle is only a development dependency and
Symfony wires no event dispatcher, they are skipped.

The integration test of row-level security, in `tests/Integration`, needs a real PostgreSQL: it boots the same kernel
with `row_level_security` enabled, creates the schema, applies the statements `phil:tenancy:rls:policies` prints, and
switches the connection to a plain role, since no policy holds a superuser. It proves that native SQL sees no row
without a tenant and only the active tenant's rows with one, even with the filter disabled; that a connection opened
again takes the active tenant; that a row of another tenant cannot be written; and that `phil:tenancy:rls:check`
passes, then fails for a superuser, a table left out of its policy and a missing table. It reads the server from
`PHIL_TENANCY_TEST_POSTGRESQL_URL`, else from `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` prints, and
is skipped without either:

```bash
PHIL_TEST_POSTGRESQL_URL='postgresql://phil:phil@127.0.0.1:15432/phil_test?serverVersion=17' vendor/bin/phpunit packages/tenancy-bundle/tests/Integration
```

## Running the tests

### From the root of the monorepo that incubates the bundle

The tests run on the root `vendor/`:

```bash
vendor/bin/phpunit packages/tenancy-bundle/tests
```

`composer packages` installs the bundle alone in a fresh directory, with `phil/ddd-bundle`
from the monorepo, checks its declared dependencies with `composer-dependency-analyser` against
`composer-dependency-analyser.php`, then runs its tests.

### Installed alone

```bash
cd packages/tenancy-bundle
composer install
vendor/bin/phpunit --no-configuration tests
```

`phil/ddd-bundle` must be resolvable, through a `path` or `vcs` repository until it is published. `doctrine/dbal`
and `phpunit/phpunit` come from `require-dev`.

## Benchmarks

The phpbench benchmarks in `tests/Benchmark` measure the Doctrine filter per generated statement and per inheritance
hierarchy, the parsing of a tenant reference, and a read through a pool scoped to the tenant against the same read on
the pool itself. Run them from the monorepo root:

```bash
composer bench -- packages/tenancy-bundle/tests/Benchmark
```

## Testing an application built on the bundle

### Rules and service

The rules are pure functions: test the application's use of them with values.

```php
$resolution = ActiveTenantRule::resolve(TenantId::fromString('wholesale'), ReachableTenants::of(TenantId::fromString('retail')));

self::assertSame('retail', $resolution->tenant()->value);
self::assertTrue($resolution->staleChoiceDropped);
```

For code that uses `ActiveTenant`, build it over two in-memory doubles:

```php
final class InMemoryActiveTenantStore implements ActiveTenantStore
{
    public int $forgotten = 0;

    public function __construct(private ?TenantId $tenant = null)
    {
    }

    public function remembered(): ?TenantId { return $this->tenant; }
    public function remember(TenantId $tenant): void { $this->tenant = $tenant; }
    public function forget(): void { $this->tenant = null; ++$this->forgotten; }
}

final readonly class FixedReachableTenants implements ReachableTenantsProvider
{
    public function __construct(private ReachableTenants $reachable)
    {
    }

    public function reachable(): ReachableTenants { return $this->reachable; }
}

$activeTenant = new ActiveTenant(new InMemoryActiveTenantStore(), new FixedReachableTenants(ReachableTenants::of(TenantId::fromString('retail'))));
```

### Adapters

- Test that the reachable-tenants adapter returns `ReachableTenants::none()` once its entitlements can no longer be
  served. That is the fail-closed property everything else relies on.
- Test that the store adapter's `forget()` really removes the choice.

### Isolation

The filter's own tests prove the SQL it adds. What only the application can prove is that its entities are
marked: write a functional test that creates rows for two tenants, activates one, and checks that a repository
query returns only that tenant's rows, and none without an active tenant. Run it against the database the
application uses in production.

In a `KernelTestCase`, enter the tenant through the context, as a request would:

```php
$context = self::getContainer()->get(TenantContext::class);

$labels = $context->within(TenantId::fromString('retail'), fn (): array => $this->invoiceLabels());

self::assertSame(['R-1', 'R-2'], $labels);
self::assertSame([], $this->invoiceLabels());
```
