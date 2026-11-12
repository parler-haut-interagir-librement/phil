# Changelog

All notable changes to `phil/idempotency` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
