# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Rebuild the events of a library | Register its `Phil\Outbox\Contract\EventCodec`: autoconfigured, or tagged `phil_outbox.codec` |
| Listen to a type without an event class | Name it in `generic_types`, and listen to `Phil\Outbox\RecordedEvent` |
| Keep the records elsewhere | Alias `Phil\Outbox\Contract\OutboxStore` to a store of the application that passes the contract tests of `Phil\Outbox\Test\OutboxStoreTestCase` |
| Tell other listeners | Name another PSR-14 dispatcher in `dispatcher`, or enable the Messenger bridge |
| Date the records by another clock | Name a clock of Symfony in `clock` |
| Decorate the outbox | Decorate `Phil\Outbox\Contract\Outbox`, keeping `record()` in the caller's transaction |

## Services

| Service | Class |
|---|---|
| `Phil\Outbox\Contract\Outbox` (public alias) | `Phil\Outbox\StoreOutbox` |
| `Phil\Outbox\Contract\OutboxStore` (alias) | `DbalOutboxStore` or `Phil\Outbox\Memory\InMemoryOutboxStore` |
| `Phil\Outbox\Codecs` | the codecs tagged `phil_outbox.codec` |
| `Phil\Outbox\Relay` (public) | the relay of the configuration |
| `Phil\Outbox\Housekeeping` (public) | the dead records, the retention, the counts |
| `phil_outbox.dispatcher` | the configured dispatcher, or `MessengerDispatcher` |
| `RelayRunner` | the runs of the command and of the scheduled trigger |
