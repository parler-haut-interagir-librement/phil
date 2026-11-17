# Stores

An `OutboxStore` keeps the records. A store that writes to the database of the library that records adds a record in
the transaction of the library's own write, so that both commit or neither does: the store shares the library's
connection.

| Store | Where |
|---|---|
| `Memory\InMemoryOutboxStore` | the memory of one process, for tests and single runs; `reset()` forgets everything |
| `DbalOutboxStore` of phil/outbox-bundle | a table of a Doctrine DBAL connection, on SQLite, MySQL, MariaDB and PostgreSQL |

## The contract

| Method | Promise |
|---|---|
| `add(OutboxRecord $record): RecordId` | Adds a pending record; a record of the same type and idempotency key already kept is left alone and its identifier returned, without aborting the caller's transaction |
| `claim(string $claim, int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseUntil): array` | Leases the available records, in the order they were added; two claims never get the same record while its lease runs; a record waits while an earlier one of its ordering key is pending or dead |
| `markDelivered()`, `release()`, `markDead()` | Settle a record of the claim, and refuse when another claim took it over |
| `find()`, `list()`, `count()` | Read the records |
| `revive()`, `discard()` | Act on a dead record only |
| `purgeDelivered(DateTimeImmutable $before, int $limit): int` | Removes delivered records settled before `$before`, in the order they were added |

Moments are kept to the second. A store keeps no state between requests that a worker would carry from one to the next.

## The contract tests

`Phil\Outbox\Test\OutboxStoreTestCase` holds the tests every store passes. An adapter's test extends it and gives
`createStore()` an empty store:

```php
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Test\OutboxStoreTestCase;

final class RedisOutboxStoreTest extends OutboxStoreTestCase
{
    protected function createStore(): OutboxStore
    {
        return new RedisOutboxStore($this->freshConnection());
    }
}
```

The tests cover the round trip of every field, the idempotency key, the order and the limit of a claim, the leases, the
order of a key, the marks under another claim, the dead records, the lists and the purge. Add the tests of what only
the adapter shows: the transaction it joins, the platform's collations.
