# The replay store

An assertion is accepted once: `ResponseProcessor` asks its `ReplayGuard` whether this is the first use of the
assertion of this issuer, and refuses it with `ReplayedAssertion` otherwise. The guard remembers each assertion until
the latest of its expiries plus the clock skew. Three guards come with the library:

| Guard | Where it remembers | Use it for |
|---|---|---|
| `DbalReplayGuard` | A table of a Doctrine DBAL connection | Any number of workers and servers: the first use is atomic. |
| `CacheReplayGuard` | A PSR-6 cache pool | One server, or a pool on a shared store, where two copies within the same instant are unlikely. |
| `InMemoryReplayGuard` | The memory of the process | Tests, or a single long-running process. |

## `DbalReplayGuard`

```sh
composer require doctrine/dbal
```

```php
use Phil\SamlSp\Message\ResponseProcessor;
use Phil\SamlSp\Store\DbalReplayGuard;

$guard = new DbalReplayGuard($connection, $clock);           // the table phil_saml_used_assertion
$guard = new DbalReplayGuard($connection, $clock, 'sso_used_assertion');

$processor = new ResponseProcessor($clock, $guard);
```

It works on PostgreSQL, MySQL, MariaDB and SQLite. The table holds one row per accepted assertion:

| Column | Type | Content |
|---|---|---|
| `assertion` | `CHAR(64)`, primary key | The SHA-256, in hexadecimal, of the length of the issuer, a colon, the issuer and the assertion ID. |
| `expires_at` | `datetime_utc_immutable`, indexed | When the assertion may be forgotten, in UTC. |

The first use is one `INSERT`, with no read before it. The primary key makes the database the judge: of two servers
that receive the same assertion at the same moment, one inserts the row and the other gets a unique violation, which
`firstUse()` answers as `false`. On PostgreSQL the insert is `INSERT … ON CONFLICT (assertion) DO NOTHING`, so a
refusal leaves an open transaction usable; MySQL, MariaDB and SQLite keep a transaction usable after a unique
violation.

- The key is hashed, so its length is fixed whatever the issuer and the ID, and no pair of issuer and ID shares it
  with another. It compares byte by byte: the column takes `ascii` and `ascii_bin` on MySQL and MariaDB, whose default
  collations fold case; PostgreSQL and SQLite compare bytes already.
- The expiry is written in UTC whatever the time zone of the instant given, and read back in UTC.
- Before the insert, the row of the same assertion is deleted when it has expired, so an assertion past its expiry
  takes no place and may be accepted again, as the other guards allow.
- The table name is up to 63 lowercase letters, digits or `_`, not starting with a digit; any other name is refused
  with an `InvalidArgumentException`.

### The migration

The application creates the table. `configureSchema()` adds it to a `Doctrine\DBAL\Schema\Schema` that lacks it and
returns a schema that has it unchanged, for a migration:

```php
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Phil\SamlSp\Store\DbalReplayGuard;
use Symfony\Component\Clock\NativeClock;

final class Version20261120000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $target = new DbalReplayGuard($this->connection, new NativeClock())->configureSchema($schema);

        foreach ($this->platform->getAlterSchemaSQL($this->connection->createSchemaManager()->createComparator()->compareSchemas($schema, $target)) as $sql) {
            $this->addSql($sql);
        }
    }
}
```

Or from a listener of the `postGenerateSchema` event of the ORM, so that the schema tools and generated migrations
see the table; or, without migrations:

```php
$manager = $connection->createSchemaManager();
$manager->migrateSchema($guard->configureSchema($manager->introspectSchema()));
```

### The purge

Expired rows stay until they are purged. `purge(DateTimeImmutable $now)` deletes the rows expired at that instant and
returns how many it deleted; run it from a scheduled task, as often as the volume of sign-ins needs — once an hour is
plenty for most applications.

```php
$guard->purge($clock->now());
```

## `CacheReplayGuard`

`CacheReplayGuard` keeps each assertion in a PSR-6 pool under the same kind of key, until its expiry. It reads, then
writes: two copies of one assertion posted within the same instant to two servers could both pass. Its pool is shared
by every worker and every server, and is not one a deploy clears, or the assertions still valid would be forgotten.

A pool local to each server — APCu, an array, files — remembers an assertion on the server that accepted it only: the
same assertion posted to another server is accepted again. Such a pool suits one server alone.
