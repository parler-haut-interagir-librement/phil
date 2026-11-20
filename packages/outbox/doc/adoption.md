# Adoption guide

This guide moves a library that writes its state and then dispatches a PSR-14 event to the outbox.

## Before: the event lost after the write

```php
public function complete(Holder $holder, Set $set): void
{
    $this->completions->add($holder, $set);                         // committed
    $this->events->dispatch(new SetCompleted($holder->id, $set));   // may never run
}
```

When the dispatcher or a listener throws, or the process stops, after the write, the completion is kept and nothing
tells the listeners again. A per-library recovery — a "delivered" flag, a scheduled retry, its own backoff — fixes one
library and leaves the others.

## After: the event recorded with the write

```php
public function __construct(
    private CompletionStore $completions,
    private Outbox $outbox,
    private Transactions $transactions,
) {}

public function complete(Holder $holder, Set $set): void
{
    $this->transactions->run(function () use ($holder, $set): void {
        $this->completions->add($holder, $set);
        $this->outbox->record(
            'sets.set_completed',
            ['set' => $set->code, 'holder' => $holder->id],
            orderingKey: 'holder:'.$holder->id,
            idempotencyKey: 'completion:'.$set->code.':'.$holder->id,
        );
    });
}
```

And a codec rebuilds the event for the relay:

```php
final readonly class SetEventsCodec implements EventCodec
{
    public function types(): array
    {
        return ['sets.set_completed'];
    }

    public function decode(OutboxRecord $record, Payload $payload): SetCompleted
    {
        return new SetCompleted($record->id->value, $payload->int('holder'), $payload->string('set'));
    }
}
```

## The steps

1. **Take the port.** The library takes `Phil\Outbox\Contract\Outbox` instead of the PSR-14 dispatcher for the events
   that follow a write. It keeps the dispatcher for the events that follow nothing persisted.
2. **Record in the transaction.** The record goes in the transaction of the write, on the same connection: with
   phil/outbox-bundle, the outbox table is on the connection of the library's own tables.
3. **Name the type.** One type per event, stable, prefixed by the library: `sets.set_completed`.
4. **Keep identifiers.** The payload holds what names the event — the set, the holder —, never the data the event
   carries, which the codec reads again, nor personal data.
5. **Choose the keys.** An ordering key when the listeners need the events of one aggregate in order; an idempotency
   key when the write itself may run twice. A key is built from identifiers, like the payload, never from personal
   data.
6. **Ship a codec.** The library ships the codec of its types; a bundle registers it, tagged `phil_outbox.codec` or
   autoconfigured.
7. **Make the event carry the record's identifier**, and tell the listeners in the library's documentation that they
   are idempotent: the relay delivers at least once.
8. **Remove the per-library recovery**: the delivered flag, the scheduled retry and the backoff of the library go, the
   relay's replace them.

## A worked example: the completions of a collection of sets

A library of collectible sets tells its listeners when a holder completes a set, so that a reward is granted. It
used to mark a completion delivered once its event was dispatched without an exception, keep the holders whose
delivery failed, and retry them on a schedule with a backoff of its own; a store without a dispatcher marked the
completion delivered with nobody told.

With the outbox, recording a completion and its record is one transaction: the completion store and the outbox table
share the library's connection. The event type is `sets.set_completed`, its payload the set's code and version and the
holder's reference; the ordering key is the holder, so that the tiers of one holder are told in the order they were
reached; the idempotency key is the completion, so that a completion checked twice is told once. The library's codec
reads the completion again by those identifiers and rebuilds the `SetCompleted` event, with the record's identifier,
which the reward listener keeps to grant a reward once. The pending deliveries, their backoff and the report of the
deliveries that keep failing are the relay's: the dead records and their last error replace the report, and an
operator tries them again once the listener is fixed.
