# Placements and suspensions in the database

The bundle keeps two things every process must agree on: where each tenant lives, and which databases and tenants are
suspended. Both can live in a table of the shared database, written by the bundle's own adapters, so that a move needs
no code and a suspension survives a cleared cache, a deployment and a restart.

```yaml
# config/packages/phil_tenancy_database.yaml
phil_tenancy_database:
    placement: dbal        # the placements in phil_tenancy_placement
    availability: dbal     # the suspensions in phil_tenancy_suspension; the default
```

| Setting | Adapter | Table |
|---|---|---|
| `placement: dbal` | `DbalPlacementStore`: `TenantPlacement`, `PlacedTenants` and `PlacementStore` | `phil_tenancy_placement` |
| `availability: dbal` (default) | `DbalTenantDatabaseAvailability` | `phil_tenancy_suspension` |
| `availability: cache` | `CacheTenantDatabaseAvailability`, on the pool `phil_tenancy_database.availability` | none; not durable |

`placement: dbal` takes no `dedicated` list: the tenants with a database of their own are recorded in the table, by a
move or by `PlacementStore::place()`.

## The tables

`phil_tenancy_placement`, one row per tenant placed:

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(64)`, primary key | The SHA-256 of the tenant reference, in hexadecimal |
| `tenant` | `VARCHAR(255)` | The tenant reference, for the operator who reads the table |
| `database_name` | `VARCHAR(63)`, nullable | The dedicated database, or `NULL` for the shared one |
| `placed_at` | `DATETIME`, default `CURRENT_TIMESTAMP` | When the placement was last recorded |

`phil_tenancy_suspension`, one row per suspension:

| Column | Type | Holds |
|---|---|---|
| `id` | `CHAR(64)`, primary key | The SHA-256 of what is suspended |
| `kind` | `VARCHAR(8)` | `shared`, `database` or `tenant` |
| `subject` | `VARCHAR(255)`, nullable | The database name or the tenant reference; `NULL` for the shared database |
| `suspended_at` | `DATETIME`, default `CURRENT_TIMESTAMP` | When the suspension was recorded |

Rows are found by the hash, never by the reference: a reference is compared byte for byte whatever the collation of the
server, where MySQL's default collation would read `Rose` and `rose` as one tenant. A tenant with no row is in the
shared database; a database or a tenant with no row is served.

A database name read back from the table is checked as `Placement::dedicated()` checks it: a row edited by hand into a
name that is not one is refused with `InvalidValue`, and the tenant is not served, rather than the name read as SQL.

## Creating them

The tables are added to the schema Doctrine compares with the database: `doctrine:migrations:diff` writes their
migration, and `doctrine:schema:update` creates them. `StoreSchemaListener` adds them to the schema of the connection
that follows the tenant, or of `store_connection` when one is named. The bundle ships no migration class: the
tables' migration is the application's, written by `doctrine:migrations:diff` into its own namespace and run in its
own order, as with the tables of Messenger's Doctrine transport, and a later change to the tables reaches the
application through the same diff.

A migration can also create them by itself:

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;

final class Version20261113000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        // Both tables, each unless it exists.
        foreach ([StoreTables::PLACEMENTS, StoreTables::SUSPENSIONS] as $name) {
            if ($schema->hasTable($name)) {
                continue;
            }

            foreach ($this->platform->getCreateTableSQL(StoreTables::configure($schema)->getTable($name)) as $sql) {
                $this->addSql($sql);
            }
        }
    }
}
```

Every database runs the same migrations, so the tables reach the dedicated databases too, where they stay empty: the
adapters read and write them in the shared database only.

## The store connection

The adapters must reach the shared database whatever tenant is entered: read through the managed connection, the table
would come from the database of the tenant being entered. By default, they share a connection of their own,
`phil_tenancy_database.store_connection`, opened with the parameters and the configuration of the managed connection,
pinned to the shared database — the primary, for a primary with replicas. It opens at its first query, and is closed at
the end of each request and each message, with the kernel's reset, so that a long-running worker never reads through
a connection the server has dropped.

An application that already has a connection on the shared database that does not follow the tenant names it:

```yaml
phil_tenancy_database:
    store_connection: platform
```

The tables then join the schema of that connection. The connection that follows the tenant is refused there.

## The cost on each request

| Question | Queries |
|---|---|
| `placementOf()`, on each entry into a tenant | One, on the primary key |
| `TenantAvailabilityGuard::check()`, on each request and each received message | Two, on the primary key |
| `place()`, `suspend…()` | One upsert: `INSERT … ON DUPLICATE KEY UPDATE` on MySQL and MariaDB, `INSERT … ON CONFLICT` on PostgreSQL |
| `resume…()` | One `DELETE` on the primary key |

Nothing is kept between two questions: a tenant moved, or a database suspended, by one process is seen by the next unit
of work of every other.

## The cache pool

`availability: cache` keeps the suspensions in `phil_tenancy_database.availability`, a pool on `cache.app` that every
tenant shares. An entry has no lifetime, but a pool that evicts entries under memory pressure, or a cache cleared at
deployment, forgets a suspension: the tenants of a database whose migration failed are then served again, on a schema
that may be half-migrated. Keep it for development, or for a pool that never evicts and is never cleared.
