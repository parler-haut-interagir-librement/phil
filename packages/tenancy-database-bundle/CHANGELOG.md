# Changelog

All notable changes to `phil/tenancy-database-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-17

The transactions left open at the end of a unit of work are rolled back, and the
tenant is left then, in a worker as in any other process.

### Added

- `RollBackOpenTransactions`, listening to `kernel.terminate`, `console.terminate`, `WorkerMessageHandledEvent` and
  `WorkerMessageFailedEvent`: a transaction still open on the connection that follows the tenant, or on
  `phil_tenancy_database.store_connection`, is rolled back at every level, with a warning that names the tenant only;
  a connection that cannot roll back is closed; a connection never opened is not created. The tenant is then left, and
  the connection returns to the shared database. `PhilTenancyDatabaseBundle::ROLLBACK_PRIORITY`.
- The integration test of two tenants taking turns in one worker, on MySQL and PostgreSQL: one kernel serving requests
  for the shared tenant, the dedicated one and the shared one again, then requests that throw in the middle of a
  transaction, each checked for its database, its identity map, its transactions and its cache.
- The documentation of what happens at the end of a unit of work and at the reset of a worker.

### Changed

- At `kernel.terminate`, after the application's listeners, the tenant of the request is left, rather than at the
  next request's reset: a listener of `kernel.terminate` with a priority below `-2048` runs outside the tenant.

## 0.1.0-alpha - 2026-11-14

### Added

- `TenantDatabaseRouter`, `TenantDatabaseMiddleware` and `TenantDatabaseDriver`: the parameters of the managed
  connection rewritten as it opens, never when the container compiles — the database name of a dedicated tenant, and
  the account, host and port of `TenantCredentials` when it gives them; a connection pinned to one database for a move.
- `TenantConnectionSwitch`, a tenant activation: the placement read on each entry, the connection closed and routed to
  the tenant's database, refused inside an open transaction (`TenantSwitchInTransaction`), the entity managers of the
  connection cleared at each change of tenant, and the shared database again on leaving and on `kernel.reset`, a
  transaction left open abandoned with a warning.
- `ConfiguredPlacements`, the `TenantPlacement` of `phil_tenancy_database.dedicated`; the `PlacedTenants` and
  `PlacementStore` ports.
- `DbalPlacementStore`, with `placement: dbal`: the placements in the table `phil_tenancy_placement` of the shared
  database, one row per tenant found by the SHA-256 of its reference, written by an upsert and read back through
  `Placement::dedicated()`; a move needs no code of the application.
- `TenantDatabaseAvailability`, with `DbalTenantDatabaseAvailability`, the default: the suspended databases and tenants
  in the table `phil_tenancy_suspension` of the shared database, kept until resumed, through a cleared cache, a
  deployment and a restart; `CacheTenantDatabaseAvailability`, with `availability: cache`, on the
  `phil_tenancy_database.availability` pool, built on `cache.app` and shared by every tenant, documented as not
  durable; `TenantAvailabilityGuard` and `TenantDatabaseUnavailable`.
- `StoreTables`, the two tables and their upsert for MySQL, MariaDB, PostgreSQL and SQLite, and `StoreSchemaListener`,
  which adds them to the schema `doctrine:migrations:diff` compares, for the connection that follows the tenant or for
  `store_connection`; no migration class is shipped, the application's diff writes the tables' migration into its own
  namespace.
- `PinnedConnection`: a connection of its own, with the parameters and configuration of the managed connection, pinned
  to one database — the source and the target of a move, and `phil_tenancy_database.store_connection`, the shared
  database of the `dbal` stores, closed with the kernel's reset.
- `RefuseUnavailableTenant`, the `503` with a `Retry-After` for a suspended tenant, before the firewall and after the
  account's tenant is resolved; `RefuseUnavailableTenantMiddleware`, which leaves the messages of a suspended tenant to
  the retry strategy.
- `MigrationRunner`, `MigrationTarget`, `MigrationResult` and `MigrationTargets`: the migration command run once per
  database in a process of its own, at most `concurrency` at a time, with a timeout; a database that fails is
  suspended and one that succeeds is resumed; the last 4 KiB of each output kept for the console, never logged.
- `MigrationStop` and the stop of a run: the running migrations sent SIGTERM, given `migrations.grace_period` seconds,
  then killed, and waited for, each interrupted one suspended, those not started left as they were and reported as
  such; a second request kills at once. An exception, or a fatal error at shutdown, stops the running migrations too:
  none outlives the console.
- The `phil:tenancy:database:migrate` command, with `--only` and `--concurrency`; stopped by SIGINT or SIGTERM, it
  waits for its migrations to stop, lists the databases it did not migrate and ends with 128 plus the signal's
  number.
- `TenantDatabaseCreator` and the `phil:tenancy:database:create` command: the database of a dedicated tenant created
  on the server of the shared connection, if missing, then migrated.
- `CopyPlan` and `CopyTable`: the tables of a tenant read from the mapping — tenant-scoped roots ordered by their
  references, the tables of a joined hierarchy, the join tables of many-to-many associations; `TenantNotCopyable` for
  tables that reference each other and keys of several columns.
- `TenantMover`, `MoveReport` and the `phil:tenancy:database:move` command: the tenant suspended, its rows copied in one
  transaction, compared table by table, its placement recorded, the source emptied unless `--keep-source`; any failure
  before that rolls the move back (`TenantNotMoved`). The sequences of PostgreSQL catch up with the copied keys.
- `DatabaseCredentials`, never dumped nor serialized, and `CredentialSeal`, the sealed format
  `ptdb.v1.xchacha20poly1305.`, bound to its database.
- The `phil_tenancy_database` configuration: `connection`, `entity_manager`, `placement` (`dbal` or a service id),
  `dedicated`, `credentials`, `availability` (`dbal`, `cache` or a service id), `store_connection`, `retry_after`,
  `migrations`, with its `grace_period`; `dedicated` beside `placement: dbal`, and a `store_connection` that follows the tenant, refused.
- Integration tests on real MySQL and PostgreSQL servers, skipped without them: two tenants of each kind in one
  request, the transaction refusal, the reset, the creation, a failed migration answered with a `503`, and moves that
  succeed, collide or lose their store; with the `dbal` stores, the placements read from the shared database while a
  dedicated tenant is entered, a move recorded in the table, and suspensions that survive a cleared cache. The MySQL
  tests read `PHIL_TENANCY_DATABASE_TEST_MYSQL_URL` with an account that may create databases. Without a server, the
  migration command in a console of its own, sent SIGTERM, sent SIGINT or ended by a fatal error while slow
  migrations run, leaves none of them running.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
