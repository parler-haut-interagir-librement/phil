# phil/tenancy-database-bundle

**A database for each tenant, or the shared one, chosen at each request on one Doctrine connection.**

phil/tenancy-database-bundle moves the Doctrine connection of a multi-tenant Symfony application to the database of the
active tenant. A tenant lives in the shared database, beside the other shared tenants and kept apart from them by the
Doctrine filter of `phil/tenancy-bundle`, or in a database of its own. The connection follows it each time a request,
a message or a command enters the tenant. One connection service serves every tenant: no connection is declared per
tenant, and a new tenant needs no deployment.

The placements and the suspensions live in two small tables of the shared database, or in the application's own
store. It refuses to change databases inside an open transaction. It clears the entity managers of the connection at each
change of tenant, so that an entity loaded for one tenant is never handed to another. In a long-running worker, the
end of each unit of work rolls back a transaction left open and returns the connection to the shared database. The
bundle runs the migrations of every database a bounded number at a time: a database that fails is suspended, its
tenants are answered with a `503` and their messages wait, while the other tenants are served. It creates the
database of a new tenant, and moves a tenant from one database to another — copied, verified, and rolled back on any
failure.

It is a Symfony bundle on Doctrine DBAL and ORM and `phil/tenancy-bundle`, whose `TenantPlacement` port it answers
from its configuration, from its table or from the application's own store. The Console component, the Process
component, Messenger and FrameworkBundle's cache pools are optional. It works on MySQL, MariaDB and PostgreSQL.

## Status

| Part | State |
|---|---|
| `TenantDatabaseRouter`, `TenantDatabaseMiddleware`, `TenantDatabaseDriver` | Implemented and tested on MySQL and PostgreSQL |
| `TenantConnectionSwitch` | Implemented and tested on MySQL and PostgreSQL; a tenant activation, reset with the kernel |
| `RollBackOpenTransactions` | Implemented and tested on MySQL and PostgreSQL; at `kernel.terminate`, `console.terminate` and the end of each Messenger message |
| `ConfiguredPlacements`, `PlacedTenants`, `PlacementStore` | Implemented and tested |
| `DbalPlacementStore`, `DbalTenantDatabaseAvailability`, `StoreTables`, `StoreSchemaListener`, `PinnedConnection` | Implemented and tested on MySQL and PostgreSQL; the default availability |
| `TenantDatabaseAvailability`, `CacheTenantDatabaseAvailability`, `TenantAvailabilityGuard` | Implemented and tested; the cache pool with `availability: cache`, not durable |
| `RefuseUnavailableTenant`, `RefuseUnavailableTenantMiddleware` | Implemented and tested; the `503` and the Messenger retry |
| `MigrationRunner`, `phil:tenancy:database:migrate` | Implemented and tested on MySQL and PostgreSQL |
| `TenantDatabaseCreator`, `phil:tenancy:database:create` | Implemented and tested on MySQL and PostgreSQL |
| `TenantMover`, `CopyPlan`, `phil:tenancy:database:move` | Implemented and tested on MySQL and PostgreSQL |
| `TenantCredentials`, `DatabaseCredentials`, `CredentialSeal` | Implemented and tested |
| `PhilTenancyDatabaseBundle` | Configuration `phil_tenancy_database` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the connection and the order of the bundles. |
| [Configuration](doc/configuration.md) | Every option, what each registers, with an example. |
| [The connection of each tenant](doc/connection.md) | How the connection follows the tenant, transactions, the identity map, workers, Messenger, the cache and row-level security. |
| [Placements and suspensions in the database](doc/stores.md) | The two tables of the shared database, their migration, the connection they are read on, and the cache pool that is not durable. |
| [Migrations and new tenants](doc/migrations.md) | Migrating every database at bounded concurrency, a failure isolated and answered with a `503`, and the database of a new tenant. |
| [Moving a tenant](doc/moving-tenants.md) | The move from one database to another, step by step, its verification, its rollback and its limits. |
| [Credentials](doc/credentials.md) | Dedicated databases reached with another account, the sealed format, and what is never logged. |
| [Extension points](doc/extension-points.md) | The ports to implement, the services to replace, and their invariants. |
| [Testing](doc/testing.md) | The bundle's tests, the servers its integration tests read, and how an application tests its isolation. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```bash
composer require phil/tenancy-database-bundle
```

```php
// config/bundles.php, after PhilTenancyBundle
Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    placement: dbal   # the placements in a table of the shared database, which a move updates
```

```bash
bin/console doctrine:migrations:diff                         # the tables of the placements and the suspensions
bin/console doctrine:migrations:migrate
bin/console phil:tenancy:database:migrate                    # every database, a bounded number at a time
bin/console phil:tenancy:database:move retail tenant_retail  # to a database created and migrated beforehand
```

## Requirements

PHP 8.5 or later with the sodium extension, Symfony 8.2 or later, Doctrine DBAL 4 and ORM 3, and
`phil/tenancy-bundle`. The databases are MySQL, MariaDB or PostgreSQL, all on the platform of the shared connection.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
