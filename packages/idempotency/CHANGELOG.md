# Changelog

All notable changes to `phil/idempotency` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.0-alpha - 2026-11-17

The next version is 0.3.0-alpha: the Messenger middleware compares the content of a message, and refuses a key reused
with another content. See [Upgrading from 0.2](doc/messenger.md#upgrading-from-02).

### Added

- `Contract\FingerprintedMessage`: a message names the fields of its intention with `idempotencyFingerprint()`.
- `Messenger\IdempotencyStamp::$fingerprint`, given to the constructor as a `PayloadFingerprint`: the producer's
  fingerprint of the intention, which the middleware compares in place of the message.
- `Messenger\IdempotencyMiddleware`'s second argument, `$compareContent`, true by default; false compares the message
  class only. `IdempotencyMiddleware::MAX_DEPTH`, how deep the content of a message is read.

### Changed

- `Messenger\IdempotencyMiddleware` fingerprints the message class and its content — the stamp's fingerprint, else the
  message's `idempotencyFingerprint()`, else every property of the message, read down to 32 levels — instead of the
  class only. A key reused with another content is refused with `UnrecoverableMessageHandlingException`, as an HTTP
  request that reuses a key with another body; before, the second message was acknowledged without its handlers. A
  message whose content cannot be read — a closure, a resource, bytes that are not UTF-8 — and that gives no fingerprint
  is refused the same way.

## 0.2.0-alpha - 2026-11-14

### Fixed

- A run that outlived its reservation can no longer overwrite the run that reserved the key after it. Before, once a
  reservation expired and a second run with the same key had started, the first run's `complete()` or `fail()`
  wrote over the second one's record: a late failure let a third request run beside the second, and a late answer
  was replayed instead of the second run's. The outcome is now written only while the key is still held under the
  token of its reservation, and a lost reservation throws `Exception\ReservationLost` instead of being written.

### Added

- `IdempotencyReservation`: the hold of one run on a key, with its scope, its key and a random token;
  `IdempotencyDecision::$reservation`, set by `IdempotencyGuard::begin()` on `Proceed`, and
  `IdempotencyDecision::reserved()`.
- `Exception\ReservationLost`, thrown by `complete()` and `fail()` of the stores and of the guard when the key is no
  longer held under the reservation. `IdempotencyGuard::run()` throws it after an operation that ended too late, and
  throws the operation's own throwable when only its failure could not be written.

### Changed

- `Contract\IdempotencyStore::reserve()` returns the `IdempotencyReservation`, or `null` when the key is taken,
  instead of a boolean; `complete()` and `fail()` take that reservation instead of the scope and the key, and write
  only while the record still holds its token. `IdempotencyGuard::complete()` and `fail()` take the reservation of
  the decision instead of the scope and the key.
- `Store\DbalIdempotencyStore`: the table of `Store\IdempotencyTable` has a `token` column, `CHAR(32)` and not null,
  and the completion and the failure are an `UPDATE … WHERE token = ?`. A table created by 0.1.0-alpha needs the
  column: add it, or drop the table and let the migration create it again, since its keys only last their retention.
- `Store\CacheIdempotencyStore` keeps the token in each entry, and checks it under the lock of the key before it
  writes an outcome. An entry written by 0.1.0-alpha holds no token: a run reserved before the upgrade loses its
  completion.

## 0.1.0-alpha - 2026-11-12

### Added

- Keys and fingerprints: `IdempotencyKey`, read from the `Idempotency-Key` header in its quoted and bare forms;
  `IdempotencyScope`; `PayloadFingerprint`, of a request or of the parts of any operation.
- Records in three states: `IdempotencyState` (in progress, completed, failed), `IdempotencyRecord` with its expiry
  and `secondsLeft()`, `StoredResponse`.
- The rule: `IdempotencyDecision` and `IdempotencyOutcome` (`Proceed`, `Replay`, `Reused`, `InProgress`); a failed
  run proceeds again with the same payload and refuses another.
- `IdempotencyGuard`, which runs the sequence of the store: `run()` around an operation, `begin()`, `complete()` and
  `fail()` for a caller that sees the start and the end apart; `IdempotentRun`.
- The port `Contract\IdempotencyStore`, and two adapters: `Store\DbalIdempotencyStore` with its table
  `Store\IdempotencyTable`, whose scope and key compare byte for byte on MySQL and MariaDB, and
  `Store\CacheIdempotencyStore` on a PSR-6 pool under a Symfony lock.
- Messenger: `Messenger\IdempotencyMiddleware`, which handles a received message once per key, and
  `Messenger\IdempotencyStamp`; the `MessageIdStamp` of Messenger is the key when no stamp is given.
- Exceptions: `IdempotencyException`, `InvalidIdempotencyValue`, `MissingIdempotencyKey`, `IdempotencyKeyReused`,
  `RequestInProgress` with its `retryAfter`.
- A fuzz target for the reader of the header, and a test of eight processes reserving the same key at once.
- The documentation in `doc/`, one file per topic, indexed by the README, in English and in French.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
