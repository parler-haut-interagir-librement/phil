# phil/outbox-bundle

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/outbox-bundle/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/outbox-bundle)

**phil/outbox in Symfony: events saved in a table of your Doctrine connection, delivered by a command or the Scheduler,
with retries and a Messenger bridge.**

phil/outbox-bundle integrates [phil/outbox](https://github.com/parler-haut-interagir-librement/outbox) with Symfony.
The libraries that record events through the outbox stay free of Symfony and of Messenger; the bundle keeps their
records next to their tables and relays them to the application's listeners. The library alone suits an application
without Symfony.

## Status

| Part | State |
|---|---|
| `DbalOutboxStore`: one table of the application's connection, joined to the caller's transaction, tested on SQLite, MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| The table in the schema the ORM generates, and `phil:outbox:schema` | Implemented and tested |
| The codecs by tag, the generic event types, the outbox, the relay and the housekeeping as services | Implemented and tested |
| `phil:outbox:relay`: bounded batches, `--stop-when-empty`, a time limit, a lock, the signals | Implemented and tested |
| The commands of the dead records and the purge of the delivered records | Implemented and tested |
| `phil:outbox:errors:clear`: the messages removed from the errors already kept | Implemented and tested |
| The scheduled trigger and the bridge to a Messenger bus | Implemented and tested |
| The Flex recipe | Implemented |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, and creating the table. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Console](doc/console.md) | The relay command and the commands of the dead records, the purge, the errors and the schema. |
| [Workers](doc/workers.md) | Running the relay under a process manager, in worker mode, or on a schedule. |
| [Messenger](doc/messenger.md) | The bridge to a bus, and when Messenger's own `outbox` and `inbox` fit better. |
| [Extension points](doc/extension-points.md) | The services to replace, the tags to add to, and when they are stable. |
| [Testing](doc/testing.md) | The bundle's tests, and the servers its integration tests read. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

```sh
composer require phil/outbox-bundle
php bin/console phil:outbox:schema --force   # or doctrine:migrations:diff with the ORM
```

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    connection: default
    generic_types: [quiz.completed]
```

```php
// In the transaction of the write it describes:
$outbox->record('quiz.completed', ['quiz' => 'q-42', 'member' => 7]);
```

```sh
php bin/console phil:outbox:relay
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later and Doctrine DBAL 4.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
