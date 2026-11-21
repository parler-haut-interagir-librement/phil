# phil/outbox

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/outbox/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/outbox)

**Never lose an event after a database write: the event is saved in the same transaction as the data, then delivered
to its listeners, with retries until they accept it.**

A library that writes its state and then dispatches an event loses the event when the listener, the dispatcher or the
process fails between the two: nothing tells the listeners again. With phil/outbox, the library records the event in
the same transaction as its write, and a relay tells the listeners afterwards, until they accept it.

phil/outbox is a plain PHP library on the PSR-14 and PSR-20 interfaces, without a dependency on Symfony Messenger.
[phil/outbox-bundle](https://github.com/parler-haut-interagir-librement/outbox-bundle) keeps the records in a table of
the application's Doctrine DBAL connection and runs the relay in Symfony.

## Status

| Part | State |
|---|---|
| `Outbox::record()` on a store, the allowlist of the codecs, the bounded payload of identifiers | Implemented and tested |
| The relay: leases, order per key, backoff, dead records, skipped records, the grace of unknown types, at-least-once delivery | Implemented and tested |
| The housekeeping: dead records inspected, tried again or discarded, retention of the delivered records | Implemented and tested |
| The store in memory, and the contract tests every store passes | Implemented and tested |
| The fuzz target of the payload reader, the benchmark of a relay batch | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Recording](doc/recording.md) | `Outbox::record()`: event types, payloads, ordering and idempotency keys, the transaction. |
| [Codecs](doc/codecs.md) | How a record becomes an event again, and the generic `RecordedEvent`. |
| [Relaying](doc/relaying.md) | The relay: leases, order per key, retries, dead records, and why listeners are idempotent. |
| [Housekeeping](doc/housekeeping.md) | The dead records, the retention of the delivered records, the counts. |
| [Stores](doc/stores.md) | The store contract, the store in memory, and the contract tests of an adapter. |
| [Adoption guide](doc/adoption.md) | Moving a library that records then dispatches to the outbox, with a worked example, and keeping it out of the way of an erasure. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests, its fuzz target and its benchmark. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/outbox
```

```php
use Phil\Outbox\Codecs;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;

$store = new InMemoryOutboxStore();
$codecs = new Codecs([new RecordedEventCodec(['quiz.completed'])]);
$outbox = new StoreOutbox($store, $codecs);

// In the transaction of the write it describes:
$outbox->record('quiz.completed', ['quiz' => 'q-42', 'member' => 7], orderingKey: 'quiz:q-42');

// Later, in a worker:
$report = new Relay($store, $codecs, $dispatcher)->relay();
```

## Requirements

PHP 8.5 or later, with the `mbstring` and `sodium` extensions.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
