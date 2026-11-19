# Codecs

A codec rebuilds the events of some types from their records, for the relay to dispatch. The types the codecs rebuild
are the allowlist of the outbox: a type without a codec is never recorded, and a record whose type lost its codec is
dead at once instead of being relayed.

```php
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;

final readonly class SetEventsCodec implements EventCodec
{
    public function __construct(private SetDefinitions $definitions) {}

    public function types(): array
    {
        return ['sets.set_completed'];
    }

    public function decode(OutboxRecord $record, Payload $payload): SetCompleted
    {
        $definition = $this->definitions->find($payload->string('set'))
            ?? throw UndeliverableRecord::because('The set is gone.');

        return new SetCompleted($record->id->value, $definition, $payload->int('holder'));
    }
}
```

`Codecs` gathers them and refuses two codecs of one type, or a type of another shape.

## What a codec does

- It reads the identifiers of the payload with `Payload::string()`, `int()`, `get()` and `has()`; a missing or mistyped
  value throws `InvalidRecord`, and the record is dead at once.
- It may read the rest of the event again from where its library keeps it.
- It throws `UndeliverableRecord` when the record can never become an event, such as when the state it names is gone
  for good: the relay moves the record to the dead records at once. Its reason is kept as the record's last error:
  identifiers only, never personal data ([The last error](housekeeping.md#the-last-error)).
- Any other exception is a failed attempt: the relay tries the record again after the delay of its backoff.
- It never runs PHP's `unserialize()`: a record holds JSON of identifiers, read back under the same bounds as a new
  payload.
- It puts the record's identifier in the event when its listeners need to tell a delivery again from a new event.

## `RecordedEvent`

`RecordedEventCodec` rebuilds the records of the types it is given as `RecordedEvent`, without an event class of their
own: the record's identifier, type, payload, ordering key, occurrence and attempt. A listener of `RecordedEvent` reads
`$event->type` and the identifiers it needs.

```php
$codecs = new Codecs([new RecordedEventCodec(['quiz.completed', 'quiz.perfect_score'])]);
```
