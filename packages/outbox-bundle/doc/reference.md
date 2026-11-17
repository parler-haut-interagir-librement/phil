# Reference

## The bundle

| Class | Role |
|---|---|
| `PhilOutboxBundle` | The configuration `phil_outbox` |
| `Infrastructure\DependencyInjection\OutboxServices` | The services of the configuration; `STORE_DBAL`, `STORE_MEMORY`, `CODEC_TAG`, `DISPATCHER` |

## Doctrine

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\DbalOutboxStore` | The records in a table of a DBAL connection, joined to the caller's transaction; a claim that leases its records in one `UPDATE` |
| `Infrastructure\Doctrine\OutboxTable` | The table's name and definition, the byte collations of the keys |
| `Infrastructure\Doctrine\OutboxSchemaListener` | Adds the table to the schema the ORM generates |
| `Infrastructure\Doctrine\Rows` | The columns of a row as PHP types |

## Console and workers

| Class | Role |
|---|---|
| `Infrastructure\Console\RelayCommand` | `phil:outbox:relay` |
| `Infrastructure\Console\DeadShowCommand` | `phil:outbox:dead:show` |
| `Infrastructure\Console\DeadRetryCommand` | `phil:outbox:dead:retry` |
| `Infrastructure\Console\DeadRemoveCommand` | `phil:outbox:dead:remove` |
| `Infrastructure\Console\PurgeCommand` | `phil:outbox:purge` |
| `Infrastructure\Console\SchemaCommand` | `phil:outbox:schema` |
| `Infrastructure\Worker\RelayRunner` | Runs the relay batch after batch, under the lock `phil_outbox.relay` |

## The scheduler and Messenger

| Class | Role |
|---|---|
| `Infrastructure\Scheduler\OutboxSchedule` | The schedule `phil_outbox` |
| `Infrastructure\Scheduler\RelayOutbox` | Its message, which carries nothing |
| `Infrastructure\Scheduler\RelayOutboxHandler` | Relays at each tick |
| `Infrastructure\Messenger\MessengerDispatcher` | The PSR-14 dispatcher that relays to a Messenger bus |
