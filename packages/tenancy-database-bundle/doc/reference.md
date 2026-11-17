# Reference

Every public type of the package, by layer.

## `Phil\TenancyDatabaseBundle\Domain`

| Type | Kind | Role |
|---|---|---|
| `ValueObject\DatabaseCredentials` | `final readonly class` | A user, a password, an optional host and port; checked, never dumped nor serialized |
| `Exception\TenantSwitchInTransaction` | `LogicException`, `DomainError` | A tenant of another database entered inside an open transaction |
| `Exception\TenantDatabaseUnavailable` | `RuntimeException`, `DomainError` | A tenant being moved, or whose database failed its last migration |
| `Exception\TenantNotMoved` | `RuntimeException`, `DomainError` | A move that did not happen and was rolled back, or a placement that cannot change |
| `Exception\TenantNotCopyable` | `RuntimeException`, `DomainError` | A mapping a move cannot copy table by table |

## `Phil\TenancyDatabaseBundle\Application`

| Type | Kind | Role |
|---|---|---|
| `Contract\PlacedTenants` | port | The tenants with a database of their own, for the migrations |
| `Contract\PlacementStore` | port, extends `TenantPlacement` | A placement a move can change |
| `Contract\TenantCredentials` | port | The account, host and port of a dedicated database |
| `Contract\TenantDatabaseAvailability` | port | The suspended databases and tenants |

## `Phil\TenancyDatabaseBundle\Infrastructure`

| Type | Kind | Role |
|---|---|---|
| `Doctrine\TenantDatabaseRouter` | `final class`, `ResetInterface` | The database the managed connection opens on; `parameters()`, `pinned()`, `route()`, `current()` |
| `Doctrine\TenantDatabaseMiddleware`, `TenantDatabaseDriver` | DBAL middleware and driver | Open each connection with the router's parameters |
| `Doctrine\TenantConnectionSwitch` | `final class`, `TenantActivation`, `ResetInterface` | Moves the connection with the tenant; refuses inside a transaction; clears the entity managers |
| `Doctrine\RollBackOpenTransactions` | `final readonly class`, event listener | Rolls back the transactions left open at the end of a request, a command or a message, then leaves the tenant |
| `Doctrine\PinnedConnection` | `final class`, static | `open()` and `shared()`: a connection of its own, with the parameters and configuration of the managed one, pinned to one database |
| `Doctrine\StoreTables` | `final class`, static | The tables `phil_tenancy_placement` and `phil_tenancy_suspension`: `configure()`, and `upsert()` for MySQL, MariaDB, PostgreSQL and SQLite |
| `Doctrine\StoreSchemaListener` | `final readonly class`, `postGenerateSchema` listener | Adds the tables of the `dbal` stores to the schema of their connection |
| `Placement\ConfiguredPlacements` | `final readonly class`, `TenantPlacement`, `PlacedTenants` | The tenants of `dedicated` |
| `Placement\DbalPlacementStore` | `final readonly class`, `PlacementStore`, `PlacedTenants` | The placements in `phil_tenancy_placement` |
| `Availability\DbalTenantDatabaseAvailability` | `final readonly class`, `TenantDatabaseAvailability` | The suspensions in `phil_tenancy_suspension`; the default |
| `Availability\CacheTenantDatabaseAvailability` | `final readonly class`, `TenantDatabaseAvailability` | The suspensions in a PSR-6 pool; not durable |
| `Availability\TenantAvailabilityGuard` | `final readonly class` | `check()`: throws `TenantDatabaseUnavailable` for a suspended tenant |
| `Http\RefuseUnavailableTenant` | `final readonly class`, request listener | The `503` and its `Retry-After` |
| `Messenger\RefuseUnavailableTenantMiddleware` | `final readonly class`, Messenger middleware | Leaves the messages of a suspended tenant to the retry strategy |
| `Migration\MigrationRunner` | `final readonly class` | The migration command once per database, at bounded concurrency, with a timeout |
| `Migration\MigrationTarget`, `MigrationResult` | `final readonly class` | One database to migrate, and how its migration ended: `interrupted()` and `notStarted()` for a stopped run |
| `Migration\MigrationStop` | `final class` | A request to stop a run, from a signal handler; a second one forces it |
| `Console\MigrationTargets` | `final readonly class` | The shared database and each dedicated database once |
| `Console\MigrateTenantDatabasesCommand` | console command | `phil:tenancy:database:migrate` |
| `Console\CreateTenantDatabaseCommand` | console command | `phil:tenancy:database:create` |
| `Console\MoveTenantCommand` | console command | `phil:tenancy:database:move` |
| `Database\TenantDatabaseCreator` | `final readonly class` | Creates a dedicated database on the server of the shared connection |
| `Move\CopyPlan`, `CopyTable` | `final class`, `final readonly class` | The tables of a tenant, in the order their rows can be inserted |
| `Move\TenantMover` | `final readonly class` | Moves a tenant, verified, or rolled back |
| `Move\MoveReport` | `final readonly class` | The rows copied to each table, and whether the source was emptied |
| `Credentials\CredentialSeal` | `final readonly class` | Seals and opens credentials: `ptdb.v1.xchacha20poly1305.` |

## The bundle

| Type | Role |
|---|---|
| `PhilTenancyDatabaseBundle` | The configuration `phil_tenancy_database`, the services above, and, with `availability: cache`, the pool `phil_tenancy_database.availability`; the constants `DBAL`, `CACHE`, `STORE_CONNECTION`, `AVAILABILITY_POOL` and `ROLLBACK_PRIORITY` |

| Service id | Class |
|---|---|
| `Phil\TenancyBundle\Application\Contract\TenantPlacement` | alias of `ConfiguredPlacements`, of `DbalPlacementStore` with `placement: dbal`, or of the service `placement` names |
| `Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability` | alias of `DbalTenantDatabaseAvailability`, of `CacheTenantDatabaseAvailability` with `availability: cache`, or of the service `availability` names |
| `phil_tenancy_database.store_connection` | a `Doctrine\DBAL\Connection` pinned to the shared database, for the `dbal` stores without `store_connection` |
| `phil_tenancy_database.availability` | the cache pool of the suspensions, on `cache.app`, with `availability: cache` |
