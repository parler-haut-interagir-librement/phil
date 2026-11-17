# Recording

A library records an event with `Outbox::record()`, inside the transaction of the write the event describes:

```php
$connection->transactional(function () use ($outbox, $holder, $set): void {
    $this->completions->add($holder, $set);
    $outbox->record('sets.set_completed', ['set' => $set->code, 'holder' => $holder->id], orderingKey: 'holder:'.$holder->id);
});
```

The record commits with the write, or rolls back with it: no event is told for a write that never happened, and no
write stays without its event. A record made outside a transaction is written on its own, as any statement is.

`record()` returns the record's `RecordId`, which the listeners receive and which stays the same across deliveries.

## The event type

The type names the event, such as `sets.set_completed`: 1 to 120 lowercase letters and digits, in words joined by `.`,
`_` or `-`. The outbox records only the types of its allowlist, which is the set of types its [codecs](codecs.md)
rebuild: `record()` throws `UnknownEventType` for any other, before anything is written. A type is part of the stored
data; renaming one is a change of the stored records, and the codec of the old name stays until they are relayed.

## The payload

The payload holds the few identifiers the codec rebuilds the event from, by name:

| Bound | Value |
|---|---|
| Values | strings, integers, booleans or null; never a float, a list, an object or a nested value |
| Names | 1 to 64 letters, digits or `_`, starting with a lowercase letter |
| A string | valid UTF-8, at most 255 bytes |
| The payload | at most 16 values, at most 4,096 bytes of JSON |

A payload carries identifiers only, never personal data nor heavy data: the codec reads the rest again from where the
library keeps it when the event is rebuilt. A delivered record is kept until its retention ends, and personal data in
it would outlive its erasure from the library's own tables. `record()` throws `InvalidRecord` for a payload out of its
bounds.

## The ordering key

An ordering key names the aggregate whose records are relayed in the order they were recorded, one after the other,
such as `holder:7`: a record waits while an earlier record of its key is not delivered. Records without a key are
relayed in any order, side by side. See [Relaying](relaying.md#the-order-of-a-key).

## The idempotency key

An idempotency key records an event once for its type: recording again a type and key already kept adds nothing and
returns the identifier of the first record. Use it when the write itself may run twice, such as a completion checked
again after a retry. The key is kept with the record, and forgotten when the record is purged after its retention.

A key, ordering or idempotency, is 1 to 190 visible ASCII characters, compared byte by byte.

## The clock

The outbox dates the record by its PSR-20 clock, `SystemClock` in UTC unless the application gives its own. Moments
are kept to the second.
