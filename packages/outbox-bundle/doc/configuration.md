# Configuration

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    store: dbal                 # "dbal", a table of a DBAL connection; "memory", one process, for tests
    connection: default         # the DBAL connection of the libraries that record
    table: phil_outbox
    clock: clock                # a clock of Symfony
    dispatcher: event_dispatcher
    generic_types: []           # types rebuilt as Phil\Outbox\RecordedEvent
    relay:
        batch_size: 100         # 1 to 1000
        lease: 60               # seconds, 1 to 3600: longer than the slowest listener
        unknown_type_grace: 3600 # seconds, 0 to 604800: a record of an unknown type is tried again meanwhile
        max_attempts: 10        # 1 to 100
        first_delay: 60         # seconds, doubled after each failure
        longest_delay: 86400    # seconds, not under the first delay
        lock: true              # one run at a time, through the lock factory
    retention: 604800           # seconds a delivered or skipped record is kept
    scheduler:
        enabled: false
        frequency: 60           # seconds between two ticks, 1 to 86400
        max_batches: 10         # batches of a tick, 1 to 1000
    messenger:
        enabled: false
        bus: messenger.default_bus
```

| Option | Default | Effect |
|---|---|---|
| `store` | `dbal` | Where the records are kept. `memory` keeps them in one process, reset between the requests of a worker: for tests only, since nothing joins a transaction |
| `connection` | `default` | The DoctrineBundle connection of the table, 1 to 64 letters, digits or `_` |
| `table` | `phil_outbox` | The table's name, 1 to 48 lowercase letters, digits or `_`, not starting with a digit |
| `clock` | `clock` | The service id of the clock of Symfony that dates the records and paces the relay |
| `dispatcher` | `event_dispatcher` | The service id of the PSR-14 dispatcher the relay tells the listeners through |
| `generic_types` | none | The event types rebuilt as `RecordedEvent`, without a codec of their own |
| `relay.batch_size` | 100 | The most records of a batch |
| `relay.lease` | 60 | The seconds a batch holds its records |
| `relay.unknown_type_grace` | 3600 | The seconds after it happened that a record of a type no codec rebuilds is tried again before it is dead: covers a worker deployed before the code that records the type; 0 buries it at once |
| `relay.max_attempts` | 10 | The failed attempts after which a record is dead |
| `relay.first_delay` | 60 | The seconds before a record that failed once is tried again |
| `relay.longest_delay` | 86,400 | The longest delay before a record is tried again |
| `relay.lock` | true | Whether one run at a time holds the lock `phil_outbox.relay` of `lock.factory`, when the application has one |
| `retention` | 604,800 | The seconds a delivered or skipped record is kept before `phil:outbox:purge` removes it |
| `scheduler.enabled` | false | A schedule `phil_outbox` that relays the outbox; see [Workers](workers.md) |
| `messenger.enabled` | false | Relays the events to a Messenger bus instead of the dispatcher; see [Messenger](messenger.md) |

## The codecs

Every service that implements `Phil\Outbox\Contract\EventCodec` is tagged `phil_outbox.codec` when autoconfigured; a
service defined by hand takes the tag itself. The types of the codecs, with the generic types, are the allowlist of
the outbox: recording another type throws `UnknownEventType`, and two codecs of one type stop the container.

```yaml
services:
    App\Outbox\SetEventsCodec:
        tags: [phil_outbox.codec]
```

## The listeners

The relay dispatches each rebuilt event through the configured dispatcher: a listener of the application listens to
the event class as to any other.

```php
#[AsEventListener]
final class GrantReward
{
    public function __invoke(RecordedEvent $event): void
    {
        // idempotent: $event->id is the same each time this record is delivered
    }
}
```
