# Changelog

All notable changes to `phil/outbox` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Added

- `OutboxRecord::OWN_EXCEPTIONS`, the namespace of the exceptions whose message an error keeps.

### Changed

- The documentation says that ordering and idempotency keys are identifiers too, never personal data, and that the
  reason of an `UndeliverableRecord` is kept as the last error.

### Security

- Privacy: `OutboxRecord::error()` kept the message of any exception, so that a mail server's refusal quoting an
  address stayed in the record's last error until it was purged or discarded. It keeps the class and code, such as
  `RuntimeException (code 550)`, and the message of the package's own exceptions only, whose text names types, keys
  and settings.

## 0.1.0-alpha - 2026-11-17

### Added

- `Contract\Outbox` and `StoreOutbox`: an event recorded in the transaction of the write it describes, by type, with a
  payload of identifiers, an optional ordering key and an optional idempotency key, dated by a PSR-20 clock.
- `Payload`: at most 16 strings, integers, booleans or nulls by name, strings of valid UTF-8 up to 255 bytes, at most
  4,096 bytes of JSON; read back from a store under the same bounds.
- `RecordId`, `OutboxRecord` and `RecordState`: the stable identifier the listeners receive, the record as a store
  keeps it, and its states, pending, delivered and dead.
- `Contract\EventCodec`, `Codecs`, `RecordedEventCodec` and `RecordedEvent`: the allowlist of the event types, and the
  events rebuilt from their records without `unserialize()`.
- `Relay` and `RelayReport`: batches claimed under a lease, so that concurrent relays never deliver the same record,
  rebuilt, dispatched through PSR-14 and marked delivered after success; at-least-once delivery, in order per ordering
  key; records of an unknown type, with a payload that is not one or declared undeliverable dead at once.
- `Backoff`: the delay before a record is tried again, doubled after each failure up to the longest delay, and the
  attempts after which it is dead.
- `Housekeeping`: the dead records listed, tried again or discarded, the delivered records purged after their
  retention, the records counted by state.
- `Contract\OutboxStore`, `Memory\InMemoryOutboxStore`, and `Test\OutboxStoreTestCase`, the contract tests every store
  passes.
- `SystemClock`, and the exceptions `InvalidRecord`, `UnknownEventType`, `UndeliverableRecord` and `InvalidSetting`
  under the marker `OutboxException`.
- A fuzz target of the payload reader and a benchmark of a relay batch.
- The documentation in `doc/`: recording, codecs, relaying, housekeeping, stores and an adoption guide.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
