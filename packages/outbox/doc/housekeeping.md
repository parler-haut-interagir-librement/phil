# Housekeeping

`Housekeeping` is what an operator does with the outbox. phil/outbox-bundle exposes it through console commands.

```php
$housekeeping = new Housekeeping($store, $clock);
```

| Method | Does |
|---|---|
| `dead(int $limit = 50, int $offset = 0)` | The dead records, the oldest first |
| `find(RecordId $id)` | One record, in any state |
| `retry(RecordId $id)` | Puts a dead record back to pending, relayed at once, its attempts back to 0 and its last error kept |
| `retryAll(int $limit = 100)` | The same for the oldest dead records |
| `discard(RecordId $id)` | Removes a dead record for good, which lets the later records of its ordering key go |
| `discardAll(int $limit = 100)` | The same for the oldest dead records |
| `purge(int $retention = 604800, int $limit = 1000)` | Removes the delivered records settled more than `$retention` seconds ago |
| `counts()` | How many records are pending, delivered and dead |

## Dead records

A dead record stays until an operator tries it again or discards it: it holds back the later records of its ordering
key meanwhile. Its last error tells why it failed; fix the cause — a listener, a missing codec, the data it names —,
then try it again. Discard a record whose event no longer matters.

## Retention

A delivered record is kept for its retention, seven days by default, then purged: the delivered records show what was
told, and keep the idempotency keys of their events meanwhile. Run the purge on a schedule; each run removes at most
`$limit` records, the oldest first, so that it stays short.
