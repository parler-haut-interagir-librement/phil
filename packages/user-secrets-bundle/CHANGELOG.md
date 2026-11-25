# Changelog

All notable changes to `phil/user-secrets-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-24

Instants are written and read in UTC, whatever the time zone of the process.

### Changed

- The development requirements accept `phil/privacy-bundle` 0.3, the minor version of its main branch and no earlier
  one, so that the package installs on its own beside that bundle.
- `doctrine/dbal` is required, no longer for development only: the Doctrine store names the types of its parameters.
- Requires the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `symfony/polyfill-php86`
  1.43), and of each development requirement (`doctrine/doctrine-bundle` 3.4).

### Security

- The instants of `phil_user_secret` — expiry, creation, change, last use, revocation — are written and read in UTC,
  whatever the time zone of the PHP process, as `datetime_utc_immutable`, and the compare-and-swap passes its instants
  with the same type: a web server, a command and a worker in different time zones read the same instant, and an expiry
  can no longer move by a few hours. The SQL type does not change; a database written outside UTC is converted once
  ([Upgrading from 0.1](doc/installation.md#upgrading-from-01)).

## 0.1.0-alpha - 2026-11-20

### Added

- `Domain\ValueObject\Owner`, `SecretName`, `ServiceName`, `SecretValue` — a secret string that prints as
  `[redacted]`, is a sensitive parameter and refuses serialization — and `SealedSecret`, whose first byte names its
  `Domain\Encryption\SealedFormat`: `Algorithm` (3), written, its second byte the `SealedAlgorithm` —
  `KeyManagementAes256Gcm` (1) —, and `KeyManagement` (2), read; `InvalidSecret` for a value out of shape.
- `Domain\Credential\ApiKey` and `OAuthToken` — access token, refresh token, expiry, scopes; expiry within a leeway;
  a refreshed token keeping the previous refresh token — behind `Credential` and `CredentialKind`.
- `Domain\Model\StoredSecret`, the row of the table `phil_user_secret` keyed by owner and name, its format byte
  repeated in a column: sealed, re-sealed, re-keyed, revoked with its value wiped, its use dated; `RevokedSecret`.
  `Domain\Contract\StoredSecretRepository`, the port of the store, which finds the secrets of another key or of
  another format in one pass past the last one read, counts them by key, reads a row again past any cache, and saves a
  row by a compare-and-swap on its sealed value.
- `Application\Contract\EnvelopeCipher`, the port of envelope encryption under a named master key, which names its
  algorithm.
  `Infrastructure\KeyManagement\KeyManagementCipher`, its one implementation: the adapter to Symfony's experimental
  KeyManagement component (`symfony/key-management`, required), a data key per value wrapped by a KMS, the row as
  additional authenticated data, every failure of the KMS turned into `UnreadableSecret` or `SealingFailed` with the
  KMS's exception as the previous one. `Application\Encryption\Envelope`: the owner, name, service and kind sealed
  beside the credential and checked when it opens, a header of format and algorithm authenticated with the row, each
  value opened by its header, values of the earlier format still opened, `key_id` the master key as configured;
  `UnknownKey`, `UnreadableSecret`, `SealingFailed`.
- `Application\UserSecrets`: store, list without values, read by a `SecretAccess` of the owner with its purpose, typed
  reads of an API key and of an OAuth token refreshed through the application's `TokenRefresher` when it expires
  within the leeway, revoke, remove, erase an owner; `SecretNotFound`, `WrongCredentialKind`, `ExpiredToken`. A
  refresh runs under the `Application\Contract\RefreshLock` when there is one, rereads the row and refreshes only when
  no other process did, and saves the token by a compare-and-swap, a few attempts at most; `RefreshConflict`. An OAuth
  token revoked, removed or erased is revoked at its service by the application's `Application\Contract\TokenRevoker`
  (RFC 7009) once it is gone locally, a failure logged and audited, never thrown.
- `Infrastructure\Lock\SymfonyRefreshLock`, the refresh lock over Symfony's Lock component (`symfony/lock`,
  optional), one lock per secret held a bounded time.
- `Application\KeyRotation`: every secret of another master key, or of an earlier format, sealed again under the
  current one in the current format, batch by batch, each value written by a compare-and-swap so that a row stored,
  refreshed or revoked meanwhile is left as the other process made it; a value that does not open named by owner and
  name while the others rotate; what is left counted by key. `Application\RotationReport`.
- Audit hooks: `SecretEvent` and `SecretEventKind` — including the revocation at the service and its failure — handed
  by `AuditTrail` to every `SecretAuditor`;
  `Infrastructure\Audit\LoggerAuditor` writes them to a PSR-3 logger.
- Doctrine and in-memory stores.
- The configuration `phil_user_secrets`: current master key, the KMS — the component's default client or a client
  named by its service —, store, entity manager, refresh leeway, lock factory and how long a refresh holds its lock,
  logged events; token refreshers, token revokers and auditors autoconfigured; the Doctrine mapping prepended.
  `Infrastructure\DependencyInjection\KmsPass` stops the container, with a message that says what to change, when the
  KMS is not defined; `RefreshLockPass` gives `UserSecrets` FrameworkBundle's lock factory, or the one named, when it
  is defined.
- The commands `phil:user-secrets:generate-key`, which prints a master key for a local `sodium://` DSN, and
  `phil:user-secrets:rotate`, which fails when a value did not open and counts what is left with `--dry-run`.
- `Infrastructure\Privacy\UserSecretsPrivacy`: the module `user_secrets` of `phil/privacy-bundle`, which exports the
  list of a person's secrets without values and erases them with the account.
- The documentation in `doc/`, one file per topic, indexed by the README; the screens of the owner left to the
  application, with what they call.
- The Flex recipe in `recipe/0.1`: both bundles registered, the local KMS and the current key configured, and an
  empty `USER_SECRETS_KEY_2026` in `.env`.
