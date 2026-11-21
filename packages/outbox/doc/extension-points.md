# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

The library is plain PHP and declares no service. The outbox, the relay and the housekeeping take these ports.

## `EventCodec`

`Phil\Outbox\Contract\EventCodec` — rebuilds the events of some types. See [Codecs](codecs.md).

| Method | Contract |
|---|---|
| `types(): array` | The event types it rebuilds: the allowlist is the union of every codec's |
| `decode(OutboxRecord $record, Payload $payload): object` | The event the relay dispatches; throws `UndeliverableRecord` when the record can never become one; never runs `unserialize()` |

## `OutboxStore`

`Phil\Outbox\Contract\OutboxStore` — where the records are kept. See [Stores](stores.md). A store of the application's
own passes the contract tests of `Phil\Outbox\Test\OutboxStoreTestCase`, adds a record in the caller's transaction,
never gives one record to two claims while its lease runs, and treats a skipped record like a delivered one: settled,
never holding back its ordering key, purged past its retention.

## `Outbox`

`Phil\Outbox\Contract\Outbox` — the port a library records through. `StoreOutbox` is the implementation; an
application may decorate it, to count the records say, as long as `record()` still runs in the caller's transaction.

## The dispatcher

Any PSR-14 `EventDispatcherInterface`: the application's own, or the bridge of phil/outbox-bundle that relays to a
Messenger bus.

## The clock and the backoff

Any PSR-20 `ClockInterface`, `SystemClock` in UTC by default. `Backoff` takes its first delay, its longest delay and
its number of attempts.
