# Changelog

All notable changes to `phil/outbox-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-24

On phil/outbox 0.2.0-alpha: skipped records and the grace of unknown types.

To update: the table keeps its columns; `state` takes the value `skipped`, which fits its 16 bytes, so no migration
is needed. Set `relay.unknown_type_grace: 0` to keep burying the records of an unknown type at once. A script that
reads the output of `phil:outbox:relay` or `phil:outbox:dead:show` finds the skipped records counted there.

### Added

- The Flex recipe in `recipe/0.2`, whose configuration names `relay.unknown_type_grace`; `recipe/0.1` keeps serving
  the versions 0.1.
- `DbalOutboxStore::markSkipped()`: a record whose state was erased is settled as `skipped`, lets the later records of
  its ordering key go, and is purged with the delivered records.
- `relay.unknown_type_grace`, 3,600 seconds by default, from 0 to 604,800: how long after it happened a record of a
  type no codec rebuilds is tried again before it is dead.
- The skipped records in the summary of `phil:outbox:relay` and in the counts of `phil:outbox:dead:show`.

### Changed

- Requires phil/outbox 0.2.0-alpha, for `OutboxRecord::OWN_EXCEPTIONS`, `RecordState::Skipped` and the grace of
  unknown types.
- `phil:outbox:purge` removes the skipped records with the delivered ones.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6), and of each development requirement
  (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4).

## 0.1.1-alpha - 2026-11-20

### Added

- `phil:outbox:errors:clear` and `DbalOutboxStore::clearErrorMessages()`: the messages removed from the errors
  already kept, their class left, the errors of the outbox's own exceptions untouched.

### Changed

- Requires phil/outbox 0.1.1-alpha, for `OutboxRecord::OWN_EXCEPTIONS`.

### Security

- Privacy: the errors written before phil/outbox 0.1.1-alpha keep the message of any exception, such as a mail
  server's refusal quoting an address, shown by `phil:outbox:dead:show`. Run `phil:outbox:errors:clear` once after
  the update.

## 0.1.0-alpha - 2026-11-17

### Added

- `DbalOutboxStore`: the records of phil/outbox in one table of the application's DBAL connection, added in the
  caller's transaction, an idempotency key kept without aborting it, a claim that leases its records in one `UPDATE`;
  tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- `OutboxTable`, `OutboxSchemaListener` and `phil:outbox:schema`: the table, its keys compared byte by byte, added to
  the schema the ORM generates or created by the command.
- The configuration `phil_outbox`: the store, the connection, the table, the clock, the dispatcher, the generic event
  types, the relay's batch, lease, attempts, delays and lock, and the retention.
- The codecs of the outbox registered by the tag `phil_outbox.codec`, autoconfigured on `EventCodec`.
- `RelayRunner` and `phil:outbox:relay`: bounded batches, `--limit`, `--stop-when-empty`, `--time-limit`, `--sleep`, a
  lock for one run at a time, and SIGINT and SIGTERM that end a run after its batch.
- `phil:outbox:dead:show`, `phil:outbox:dead:retry`, `phil:outbox:dead:remove` and `phil:outbox:purge`.
- The scheduled trigger: the schedule `phil_outbox`, its message `RelayOutbox` and `RelayOutboxHandler`.
- `MessengerDispatcher`: the events of the outbox relayed to a Messenger bus.
- The Flex recipe in `recipe/0.1`.
- A benchmark of a relay batch on the DBAL store.
- The documentation in `doc/`: installation, configuration, console, workers and Messenger.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
