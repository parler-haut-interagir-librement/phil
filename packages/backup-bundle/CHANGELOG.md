# Changelog

All notable changes to `phil/backup-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-14

### Added

- `warn_after` (default 3600 seconds, null or 0 never): a client tool still running after it is logged as a warning
  on the `backup` channel, once, and announced by the `BackupToolRunningLong` event — its tool name, process id and
  the threshold, identifiers only — so that monitoring can tell a slow backup from a stuck one.
- `grace_period` (default 10 seconds): how long a client tool past its `timeout` is given to stop after SIGTERM
  before it is sent SIGKILL.
- `ProcessCommandRunner` takes the warning threshold, the grace period, an optional PSR-3 logger and an optional
  event dispatcher after its existing arguments.

### Changed

- A client tool past its `timeout` is sent SIGTERM, then SIGKILL after the grace period, and the backup fails with
  a `BackupFailed` naming the tool and the limit, instead of Symfony Process's `ProcessTimedOutException` after an
  immediate SIGKILL. `timeout: 0` still means no limit; a negative `timeout`, `warn_after` or `grace_period` fails
  the container build.

## 0.1.0-alpha - 2026-11-11

### Added

- `DatabaseClients`: the `phil/backup` client of a Doctrine DBAL connection — SQLite, MySQL, MariaDB or
  PostgreSQL — given its parameters, without connecting; an in-memory SQLite database or another platform refused.
- `ProcessCommandRunner`: the client tools found on the `PATH` or where the configuration says, run with Symfony
  Process without a shell, with an optional time limit, their output given back.
- `PhilBackupBundle`: the keys `connection`, `directory`, `binaries`, `timeout`, `key`, `allow_unencrypted`,
  `media`, `rotation`, `restore` and `verify`; a configuration without a key refused unless `allow_unencrypted` is
  true; `Backups` over the client, the directory, the cipher when a key is given, the media
  archive and the rotation when one is set, and the `RestoreGuard`; with a key, backups in clear refused on restore
  and verification unless `allow_unencrypted` is true; a bad media name fails the build.
- `phil:backup:create` (`--label`, `--no-media`, `--no-rotate`): a new set, then the expired ones removed.
- `phil:backup:restore` (`--confirm`, `--database`, `--no-media`, `--prune`): refused in production unless
  `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` names the database, without `--confirm`, or without the database's name
  typed again, asked for on an interactive console; the current content backed up as `before-restore` first, the
  latest `restore.keep_before_restore` of these kept once the restore succeeds; the media files the set does not
  hold removed with `--prune`.
- `phil:backup:verify`: the restore test, into a temporary SQLite file or the scratch database of
  `verify.database`, with the media archive read whole.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
