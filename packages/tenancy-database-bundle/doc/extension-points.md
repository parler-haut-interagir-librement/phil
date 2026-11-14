# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Extension point | Kind | Implemented by | Wiring |
|---|---|---|---|
| `Phil\TenancyBundle\Application\Contract\TenantPlacement` | port | the bundle (`ConfiguredPlacements`, `DbalPlacementStore`), or the application | `placement`, or `dedicated` |
| `Application\Contract\PlacedTenants` | port | the same service as `TenantPlacement` | none; the migrations check for it |
| `Application\Contract\PlacementStore` | port, extends `TenantPlacement` | the bundle (`DbalPlacementStore`), or the application | `placement`; the moves check for it |
| `Application\Contract\TenantCredentials` | port | the application | `credentials` |
| `Application\Contract\TenantDatabaseAvailability` | port | the bundle (`DbalTenantDatabaseAvailability`, `CacheTenantDatabaseAvailability`), or the application | `availability` |
| `migrations.command` | configuration | the application's migration command | any command that migrates the database the connection is in |

## `TenantPlacement`, `PlacedTenants` and `PlacementStore`

`placement: dbal` gives the three ports on one service, `DbalPlacementStore`, over a table of the shared database
([Placements and suspensions in the database](stores.md)). An application that keeps its placements elsewhere — in its
own table of tenants, beside their plan — implements them on one service:

| Method | Contract |
|---|---|
| `placementOf(TenantId $tenant): Placement` | `Placement::shared()` or `Placement::dedicated($name)`; an unknown tenant is shared |
| `placedTenants(): iterable<TenantId>` | Every tenant with a database of its own; shared tenants may be left out |
| `place(TenantId $tenant, Placement $placement): void` | Durable once it returns: the next `placementOf()`, in any process, reads it |

Invariants:

- `placementOf()` is asked on each entry into a tenant: one indexed query, or a cache that `place()` invalidates.
- Never on the managed connection: it would read the table from whatever database the connection is in.
- `place()` is called last in a move, after the copy is verified, and again with the old placement if the move rolls
  back: it must be idempotent.

```php
final readonly class TenantPlacements implements PlacementStore, PlacedTenants
{
    public function __construct(
        private Connection $platform,
    ) {
    }

    public function placementOf(TenantId $tenant): Placement
    {
        $database = $this->platform->fetchOne('SELECT database_name FROM tenant_placement WHERE tenant = ?', [$tenant->value]);

        return \is_string($database) ? Placement::dedicated($database) : Placement::shared();
    }

    public function place(TenantId $tenant, Placement $placement): void
    {
        $this->platform->executeStatement('DELETE FROM tenant_placement WHERE tenant = ?', [$tenant->value]);

        if (null !== $placement->database) {
            $this->platform->insert('tenant_placement', ['tenant' => $tenant->value, 'database_name' => $placement->database]);
        }
    }

    public function placedTenants(): iterable
    {
        foreach ($this->platform->fetchFirstColumn('SELECT tenant FROM tenant_placement') as $tenant) {
            yield TenantId::fromString((string) $tenant);
        }
    }
}
```

## `TenantCredentials`

| Method | Contract |
|---|---|
| `credentialsFor(string $database): ?DatabaseCredentials` | The account, and optionally the host and port, of one dedicated database; `null` for those of the shared connection |

Asked each time the connection opens on that database, never for the shared one. Store the credentials sealed: see
[Credentials](credentials.md).

## `TenantDatabaseAvailability`

| Method | Contract |
|---|---|
| `suspendDatabase(Placement)`, `resumeDatabase(Placement)`, `isDatabaseSuspended(Placement)` | A database whose last migration failed |
| `suspendTenant(TenantId)`, `resumeTenant(TenantId)`, `isTenantSuspended(TenantId)` | A tenant being moved |

Invariants: shared by every process that serves the application — web servers, workers and the console — and by every
tenant; `isDatabaseSuspended()` and `isTenantSuspended()` are asked on each request and each received message, so keep
them cheap. A suspension forgotten serves a tenant whose database may be half-migrated: the default adapter,
`DbalTenantDatabaseAvailability`, keeps them in a table of the shared database; `CacheTenantDatabaseAvailability`, on a
cache pool, suits only a pool that never evicts and is never cleared.

## `migrations.command`

Any console command that migrates the database the managed connection is in when it runs: Doctrine Migrations, a
schema update, the application's own. It ends with `0` on success; any other code, or a timeout, suspends the database.
It receives `--tenant=` for a dedicated database, which `phil/tenancy-bundle` adds to every command.
