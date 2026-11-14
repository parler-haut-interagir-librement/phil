# Migrations and new tenants

## Migrating every database

```sh
bin/console phil:tenancy:database:migrate
bin/console phil:tenancy:database:migrate --only=shared --only=tenant_wholesale --concurrency=8
```

The command migrates the shared database, then each dedicated database once, in the order of their names. Each runs
`migrations.command` in a process of its own: the shared database in no tenant, a dedicated database with
`--tenant=` the first of its tenants, which `phil/tenancy-bundle` enters and the connection follows. At most
`migrations.concurrency` processes run at a time.

The dedicated databases come from `PlacedTenants::placedTenants()`, which the placement service implements; several
tenants that share one dedicated database migrate it once.

| Outcome | What follows |
|---|---|
| The process ends with `0` | The database is resumed, if it was suspended. |
| It ends with another code | The database is suspended: its tenants are answered with a `503`, their messages wait. The others carry on. |
| It runs past `migrations.timeout` | It is stopped, and the database is suspended. |

The command prints each database as it finishes, and the last 4 KiB of what a failed migration wrote. It ends with
`1` while any database failed, so that a deployment can stop. Running it again — or with `--only` the failed
database — resumes each database that then migrates.

What a migration writes is shown on the console and never logged: a connection error may name a host or a user.

## Stopping the command

The command listens to SIGINT and SIGTERM — Ctrl+C, a deployment that is cancelled, a container that is stopped — so
that no migration outlives it:

1. It starts no other migration.
2. It sends SIGTERM to each migration still running, waits up to `migrations.grace_period` seconds for them, then sends
   SIGKILL to those still running, and waits for them all. A second signal skips the wait.
3. Each migration it stopped may have left its database half-migrated: the database is suspended, as after a failure.
   A migration that ends with `0` in the meantime counts as done. Each database not started yet is left as it was.
4. It prints what it did not migrate, and ends with 128 plus the signal's number: `143` for SIGTERM, `130` for SIGINT.

```
✓ shared
✗ tenant_retail was stopped before it finished; its tenants are suspended.
- tenant_wholesale was not started.
1 of 3 databases migrated.
Stopped by signal 15; not migrated: tenant_retail, tenant_wholesale.
```

Run it again, or with `--only` the databases it lists, to migrate them. The runner stops its migrations the same way
when an exception goes through it, and at shutdown after a fatal error. A SIGKILL sent to the command itself cannot be
caught: its migrations then run on until they end, and their databases keep the state of their last migration.

`MigrationRunner::run()` takes the `MigrationStop` to request from code that runs it elsewhere, such as a worker's own
signal handler.

## A suspended tenant

`RefuseUnavailableTenant` answers a request in a suspended tenant — its database failed its last migration, or the
tenant is being moved — with a `503 Service Unavailable` and a `Retry-After` of `retry_after` seconds. The visitor reads
only that the site is under maintenance; the reason stays in the exception chain, for the logs. The check runs as soon
as the host's tenant is entered, before the firewall reads an account from the database, and again once the account's
tenant is resolved.

Commands are never refused, so that an operator can migrate again.

The suspensions are kept by `TenantDatabaseAvailability`. The default keeps them in the table `phil_tenancy_suspension`
of the shared database, where they survive a cleared cache, a deployment and a restart, until a migration of the
database succeeds. `availability: cache` keeps them in a cache pool instead, which an eviction or a cleared cache empties
(see [Placements and suspensions in the database](stores.md)).

## The database of a new tenant

```sh
bin/console phil:tenancy:database:create wholesale
bin/console phil:tenancy:database:create wholesale --no-migrate
```

The tenant's placement must name a dedicated database. The command creates it on the server of the shared connection,
if it does not exist yet, with the account of the shared connection, then migrates it as the migrate command does; a
failed migration suspends it. A tenant in the shared database needs nothing.

The database name comes from `Placement::dedicated()`, which accepts lowercase ASCII letters, digits and underscores
only, and is quoted as an identifier besides. A database on another server, reached through `TenantCredentials`, is
created by that server's operator; the command then only migrates it.

A tenant's database is created in no tenant: `TenantDatabaseCreator` refuses while the connection is in a tenant's
database.
