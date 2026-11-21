# Changelog

All notable changes to `phil/outbox` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.0-alpha: a record whose state was erased is skipped instead of dead, a record of an unknown
type is tried again during a grace, and `Payload::of()` refuses text.

To update: a store of your own implements `OutboxStore::markSkipped()`, claims past the skipped records as past the
delivered ones, and purges them with `purgeDelivered()`; `OutboxStoreTestCase` checks it. A payload that carries a
string with `@` or whitespace goes through `Payload::withText()`, or better, carries an identifier instead. Code that
reads `Housekeeping::counts()` or builds a `RelayReport` by position finds `skipped` added. Set the last argument of
`Relay` to 0 to keep burying the records of an unknown type at once.

### Added

- `OutboxRecord::OWN_EXCEPTIONS`, the namespace of the exceptions whose message an error keeps.
- `Exception\ForgottenRecord`, thrown by a codec when the state a record names was erased on purpose, and
  `RecordState::Skipped`, the state the relay gives such a record: no listener receives it, no attempt is counted, it
  is never dead, it lets the later records of its ordering key go, and it is purged with the delivered records.
- `RecordState::isSettled()`, `RelayReport::$skipped`, and `skipped` in `Housekeeping::counts()`.
- `OutboxStore::markSkipped()`, in `InMemoryOutboxStore` and in the contract tests of `OutboxStoreTestCase`.
- The grace of unknown types, the last argument of `Relay`, `Relay::UNKNOWN_TYPE_GRACE` (one hour) and
  `Relay::MAX_UNKNOWN_TYPE_GRACE` (seven days): a record of a type no codec rebuilds is tried again until the grace,
  counted from when it happened, is over.
- `Payload::withText()` and `Payload::TEXT_PATTERN`.
- An Erasure section in the [Adoption guide](doc/adoption.md).

### Changed

- `Payload::of()`, and so `Outbox::record()`, refuse a string with `@` or whitespace, Unicode spaces included: a
  payload carries identifiers, never an address, a name or a sentence. A payload read back from a store still takes
  them.
- A record of an unknown type is no longer dead at once: it is tried again during the grace of unknown types.
- `OutboxStore::claim()` and `purgeDelivered()` treat a skipped record like a delivered one.
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
