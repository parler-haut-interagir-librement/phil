# Changelog

All notable changes to `phil/outbox-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
