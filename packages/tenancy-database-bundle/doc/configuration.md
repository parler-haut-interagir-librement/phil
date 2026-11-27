# Configuration

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    connection: default                 # the connection that follows the tenant
    entity_manager: default             # the mapping a move copies
    placement: null                     # null for the tenants below, dbal for the table, or a TenantPlacement service
    dedicated:                          # the tenants with a database of their own
        wholesale: tenant_wholesale
        export: tenant_export
    credentials: null                   # a TenantCredentials service
    availability: dbal                  # dbal for the table, cache for the pool, or a TenantDatabaseAvailability service
    store_connection: null              # a connection on the shared database for the dbal stores
    retry_after: 120                    # the Retry-After of a 503, in seconds
    migrations:
        console: '%kernel.project_dir%/bin/console'
        command: ['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration']
        concurrency: 4                  # 1 to 32
        timeout: 3600                   # seconds; 0 for no limit
        grace_period: 10                # seconds between SIGTERM and SIGKILL when the command is stopped
```

| Key | Default | What it does |
|---|---|---|
| `connection` | `default` | The Doctrine connection whose driver is wrapped by `TenantDatabaseMiddleware`, and that `TenantConnectionSwitch` moves. |
| `entity_manager` | `default` | The entity manager whose tenant-scoped entities `TenantMover` copies. |
| `placement` | `null` | Where the placements are read. `null`: `ConfiguredPlacements` reads `dedicated`, and nothing can move. `dbal`: `DbalPlacementStore` reads and writes the table `phil_tenancy_placement` of the shared database ([Placements and suspensions in the database](stores.md)). Any other value is the id of the application's `TenantPlacement` service, which may also implement `PlacedTenants`, for the migrations, and `PlacementStore`, for the moves. |
| `dedicated` | `{}` | The tenants with a database of their own, by reference, and the name of that database. Every other tenant is in the shared database. Read only when `placement` is `null`; refused with `placement: dbal`; checked as the container compiles. |
| `credentials` | `null` | The id of a `TenantCredentials` service, for a dedicated database reached with another account, host or port. |
| `availability` | `dbal` | Where the suspensions are kept. `dbal`: `DbalTenantDatabaseAvailability`, in the table `phil_tenancy_suspension` of the shared database. `cache`: `CacheTenantDatabaseAvailability`, in the `phil_tenancy_database.availability` pool, which an eviction or a cleared cache empties. Any other value is the id of a `TenantDatabaseAvailability` service. |
| `store_connection` | `null` | The Doctrine connection on the shared database that the `dbal` stores use; it must not follow the tenant, so `connection` is refused. Without it, the stores share `phil_tenancy_database.store_connection`, a connection of their own pinned to the shared database of `connection`. |
| `retry_after` | `120` | The seconds of the `Retry-After` header of the `503` a suspended tenant is answered with; at least 1. |
| `migrations.console` | `%kernel.project_dir%/bin/console` | The console each migration runs. |
| `migrations.command` | `doctrine:migrations:migrate --no-interaction --allow-no-migration` | The command, and its arguments, that migrates the database the connection is in; `--tenant=` is added for a dedicated database. Any command works: `doctrine:schema:update --force` for a prototype. |
| `migrations.concurrency` | `4` | The migrations that run at a time, from 1 to 32; `--concurrency` overrides it for one run. |
| `migrations.timeout` | `3600` | The seconds each migration may run before it is stopped and counted as failed; `0` for no limit. |
| `migrations.grace_period` | `10` | The seconds a running migration is given to stop after SIGTERM, when the command is stopped by a signal, before it is sent SIGKILL; `0` kills at once. See [Stopping the command](migrations.md#stopping-the-command). |

## What the bundle registers

| Service | Registered |
|---|---|
| `TenantDatabaseRouter` | always; reset with the kernel |
| `TenantDatabaseMiddleware` | always, tagged `doctrine.middleware` for `connection` |
| `TenantConnectionSwitch` | always, tagged `phil_tenancy.activation` and reset with the kernel |
| `RollBackOpenTransactions` | always, listening to `kernel.terminate`; to `console.terminate` with the Console component, and to `WorkerMessageHandledEvent` and `WorkerMessageFailedEvent` with Messenger. See [Workers](connection.md#workers) |
| `ConfiguredPlacements`, aliased as `TenantPlacement` | when `placement` is `null` |
| `DbalPlacementStore`, aliased as `TenantPlacement` | with `placement: dbal`; for a service id, `TenantPlacement` is an alias of that service |
| `DbalTenantDatabaseAvailability`, aliased as `TenantDatabaseAvailability` | with `availability: dbal`, the default |
| `CacheTenantDatabaseAvailability`, aliased as `TenantDatabaseAvailability` | with `availability: cache`; for a service id, `TenantDatabaseAvailability` is an alias of that service |
| `phil_tenancy_database.store_connection` | with a `dbal` store and no `store_connection`; closed with the kernel's reset |
| `StoreSchemaListener` | with a `dbal` store, listening to `postGenerateSchema` for the connection whose schema holds the tables |
| `TenantAvailabilityGuard` | always |
| `RefuseUnavailableTenant` | always, listening to `kernel.request` twice: after `ResolveHostTenant` and before the firewall, then after `ResolveActiveTenant` |
| `RefuseUnavailableTenantMiddleware` | when Messenger is installed; autoconfigured, on every bus by its `#[AsMessageMiddleware]`, after the tenant middleware |
| `TenantDatabaseCreator`, `TenantMover` | always |
| `MigrationRunner`, `MigrationTargets` and the three commands | when the Console and Process components are installed |

With FrameworkBundle and `availability: cache`, the bundle declares the cache pool `phil_tenancy_database.availability`
on `cache.app`, and lists it among the global pools of `phil_tenancy.cache`, so that every tenant reads the same
suspensions.

## Messenger

`RefuseUnavailableTenantMiddleware` carries Messenger's `#[AsMessageMiddleware(bus: '*')]`, and the bundle registers
it autoconfigured: it goes on every bus, after the tenant middleware of `phil/tenancy-bundle`, which enters the tenant
of each received message, and before the Doctrine middleware that opens, pings or closes a connection. Nothing needs
listing. A bus whose configuration lists both keeps them where the configuration puts them:

```yaml
framework:
    messenger:
        buses:
            messenger.bus.default:
                middleware:
                    - Phil\TenancyBundle\Infrastructure\Messenger\TenantMiddleware
                    - Phil\TenancyDatabaseBundle\Infrastructure\Messenger\RefuseUnavailableTenantMiddleware
```
