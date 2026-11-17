# Installation

```sh
composer require phil/outbox
```

| Requirement | Version |
|---|---|
| PHP | 8.5 or later |
| `ext-mbstring` | to cut the errors kept on a character boundary |
| `ext-sodium` | to write the random identifiers and claim tokens |
| `psr/clock` | 1.0, the clock the records are dated by |
| `psr/event-dispatcher` | 1.0, the dispatcher the relay tells the listeners through |

The library declares no service. A Symfony application installs
[phil/outbox-bundle](https://github.com/parler-haut-interagir-librement/outbox-bundle), which keeps the records in a
table of its Doctrine DBAL connection and wires the outbox, the relay and the commands. Another application builds
the objects itself:

```php
$codecs = new Codecs([new MyEventsCodec(), new RecordedEventCodec(['quiz.completed'])]);
$outbox = new StoreOutbox($store, $codecs, $clock);
$relay = new Relay($store, $codecs, $dispatcher, $clock, new Backoff(), lease: 60);
$housekeeping = new Housekeeping($store, $clock);
```

`$store` is an `OutboxStore` that writes to the same database as the library that records: see [Stores](stores.md).
To run the contract tests of a store, require `phpunit/phpunit` in development.
