# Reference

## Recording

| Type | Role |
|---|---|
| `Contract\Outbox` | The port a library records through: `record(string $type, array $payload = [], ?string $orderingKey = null, ?string $idempotencyKey = null): RecordId` |
| `StoreOutbox` | The outbox on a store: checks the allowlist and the bounds, dates the record, adds it |
| `Payload` | The identifiers of a record by name, bounded; `of()`, which refuses a string with `@` or whitespace, `withText()`, `fromJson()`, `toJson()`, `get()`, `has()`, `string()`, `int()`; `TEXT_PATTERN` |
| `RecordId` | The stable identifier of a record, 32 lowercase hexadecimal digits; `generate()`, `fromString()`, `equals()` |
| `SystemClock` | The clock of the system, in UTC |

## Records and stores

| Type | Role |
|---|---|
| `OutboxRecord` | A record as a store keeps it: type, payload JSON, keys, occurrence, availability, state, attempts, last error, settlement; `pending()` checks a new one, `payload()` reads its payload, `error()` writes the class and code of an exception, and the message of the package's own only; `OWN_EXCEPTIONS`, the namespace of those |
| `RecordState` | `Pending`, `Delivered`, `Skipped` or `Dead`; `isSettled()`, true for the delivered and skipped records |
| `Contract\OutboxStore` | Where the records are kept |
| `Memory\InMemoryOutboxStore` | The records in the memory of one process |
| `Test\OutboxStoreTestCase` | The contract tests every store passes |

## Codecs

| Type | Role |
|---|---|
| `Contract\EventCodec` | Rebuilds the events of some types |
| `Codecs` | The codecs by type: the allowlist |
| `RecordedEventCodec` | Rebuilds the records of the types it is given as `RecordedEvent` |
| `RecordedEvent` | The record as it is: identifier, type, payload, ordering key, occurrence, attempt |

## Relaying and housekeeping

| Type | Role |
|---|---|
| `Relay` | Relays a batch: lease, rebuild, dispatch, mark; `BATCH`, `MAX_BATCH`, `LEASE`, `UNKNOWN_TYPE_GRACE`, `MAX_UNKNOWN_TYPE_GRACE` |
| `RelayReport` | What a run did; `isEmpty()`, `plus()` |
| `Backoff` | When a record is tried again, and after how many attempts it is dead |
| `Housekeeping` | The dead records, the retention, the counts; `RETENTION` |

## Exceptions

Every exception implements `Exception\OutboxException`.

| Exception | Thrown when |
|---|---|
| `Exception\InvalidRecord` | A type, a key or a payload is out of its bounds, or a payload read back is not one |
| `Exception\UnknownEventType` | No codec rebuilds a type: `record()` refuses it, and the relay tries a record of it again during the grace of unknown types |
| `Exception\UndeliverableRecord` | A codec declares that a record can never become an event |
| `Exception\ForgottenRecord` | A codec declares that what a record names was erased on purpose: the record is skipped |
| `Exception\InvalidSetting` | A setting of the relay, of its backoff, of the retention or of the codecs is out of its bounds |
