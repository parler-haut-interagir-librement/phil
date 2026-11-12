# Stores

A store implements `Contract\IdempotencyStore`. The library ships two; both keep the fingerprint, never the payload,
and both forget an expired record on their own.

## `Store\DbalIdempotencyStore`

```php
new DbalIdempotencyStore(Connection $connection, ClockInterface $clock)
```

The keys live in the table `phil_idempotency` (`Store\IdempotencyTable::NAME`): scope, key, fingerprint, state, the
status, headers and body of the answer, and the expiry, with a primary key on scope and key and an index on the
expiry.

- **The reservation is atomic**: it deletes the key's expired or failed row, then inserts the key unless it is
  there, in one statement that leaves a live row alone without aborting the transaction — `ON CONFLICT DO NOTHING`
  on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite. Elsewhere, a plain insert
  whose unique violation is a lost reservation.
- **Expiries are written in UTC** and read back in UTC, whatever the zone of PHP. An expired row is never read.
- `purge(): int` forgets the expired rows; run it from a scheduled task.
- `IdempotencyTable::configure(Schema $schema, ?AbstractPlatform $platform = null): Schema` adds the table to a
  schema, for a migration or a schema listener; a schema that has it is returned as is. Scopes and keys compare byte
  for byte: given a MySQL or MariaDB platform, whose default collations fold case and accents, the two columns take
  `utf8mb4_bin`.

## `Store\CacheIdempotencyStore`

```php
new CacheIdempotencyStore(CacheItemPoolInterface $pool, LockFactory $locks, ClockInterface $clock)
```

The keys live in a PSR-6 pool. A pool has no atomic insert, so each write takes the lock of its key from the
Symfony Lock component, for at most 10 seconds: the read and the write of a reservation are one step.

- **The pool and the lock store are shared** by every server that answers the same keys: Redis, Memcached, a
  database. A local filesystem behind a load balancer lets two servers reserve the same key.
- Each entry holds scalars only, never an object, under `phil_idempotency.` and a SHA-256 of the scope and the key:
  a scope or a key may hold characters PSR-6 reserves.
- Each entry expires with its record, so the pool forgets it on its own. The record's expiry is checked against the
  application's clock as well.

## Which one

The table is the default: durable, atomic without a lock, and inside the transaction of the operation when the
connection is shared. The cache suits keys that may be lost with the cache, such as the double submission of a
form, or an application without a database.

Next: [Messenger](messenger.md).
