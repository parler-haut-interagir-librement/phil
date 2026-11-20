# Storage

A `UsageStore` keeps the counters: one per subject, meter and counter start — a period, or a bucket of a rolling
window —, with when it stops counting in any window. A store makes three promises: an increment under a ceiling is
atomic, so two at the same moment are both counted or one is refused, never lost; a key recorded once is never counted
twice; a refused increment leaves neither its amount nor its key behind. A refund gives back at most what its key
counted, however often it is asked.

## In memory

`InMemoryUsageStore` keeps the counters in an array, for tests and for a single process — a command, a worker that
meters what it does in one run. They are lost with the object.

## In a database

`Dbal\DbalUsageStore` keeps them in two tables of a Doctrine DBAL connection, on PostgreSQL, MySQL, MariaDB or SQLite;
any other platform is refused at the first increment.

| Table | Key | Columns |
|---|---|---|
| `phil_usage_counter` | `subject`, `meter`, `starts_at` | `used`, `expires_at` (indexed) |
| `phil_usage_key` | `subject`, `meter`, `idempotency_key` | `starts_at`, `amount`, `refunded`, `expires_at` (indexed) |

Instants are Unix timestamps in `BIGINT` columns: no time zone is stored, and comparisons are integer comparisons. The
names are the constructor's second and third arguments: up to 63 lowercase letters, digits or `_`, not starting with
a digit.

**An increment is one transaction**: the key's row is inserted unless it is there, then the counter's row at zero
unless it is there — `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE`
on SQLite, so that a conflict never aborts the transaction —, then one statement adds the amount:

```sql
UPDATE phil_usage_counter SET used = used + ?
 WHERE subject = ? AND meter = ? AND starts_at = ? AND used + ? <= ?
```

That statement is the atomic step: the database locks the row while it checks and writes it, so a second request
waits for the first and sees its count. No row changed means refused; the transaction is rolled back. A key already
there means a duplicate; the transaction is rolled back before anything is counted. Inside a transaction of the
application, the increment nests in it and is undone with it.

**A refund is one transaction too.** The key's row — which keeps the start of the counter it went to, the amount it
counted and what was given back of it — is read and locked, `SELECT … FOR UPDATE` on PostgreSQL, MySQL and MariaDB;
SQLite has a single writer and takes the database's lock at the first write. What is left to give is computed from
that row, then the counter goes down by it where it still holds that much, and the key's `refunded` goes up by it. A
second refund of the same key waits for the first and finds nothing left; nothing to give, or a counter already
purged, rolls the transaction back. Inside a transaction of the application, the refund nests in it.

**Rolling windows** take what the earlier buckets hold off the limit before the conditional update of the current
bucket. Earlier buckets no longer change, so the check stays exact, except for two requests that straddle a bucket
boundary within the same instant — the window may then pass its limit by the amount of one of them.

## Creating the tables

`configureSchema()` adds both tables to a schema that lacks them. With the ORM, `Dbal\UsageSchemaListener` does it on
the `postGenerateSchema` event for the store's connection, so that `doctrine:migrations:diff` writes the migration and
`doctrine:schema:validate` keeps the tables:

```php
$store = new DbalUsageStore($connection);
$eventManager->addEventListener('postGenerateSchema', new UsageSchemaListener($connection, $store));
```

Without the ORM, a migration calls `$store->configureSchema($schema)` on the schema it builds.

## The purge

`UsageMeter::purge()` — or the store's `purge($now)` — forgets the counters, and their keys, that stopped counting:
the end of a period, or the end of the window of a bucket. It returns how many rows went. Nothing breaks without it,
but the tables grow by one row per subject, meter and period; a daily task keeps them small.
