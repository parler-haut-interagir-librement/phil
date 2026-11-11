# Changelog

All notable changes to `phil/backup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `DatabaseIdentity`: the platform, host and name of a database, `describe()`, `isFile()` and `typedName()`, the
  name a person types again to confirm a restore.
- `DatabaseClient` and `CommandRunner` (ports): backup, restore, `withDatabase()` for a restore into another
  database, and `check()` after a restore; tools found and run by the application, their output given back.
- `SqliteClient`, `MysqlClient` and `PostgresqlClient` over the client tools: MySQL and MariaDB read their
  credentials from an owner-only option file, the only one they read, removed once the tool has run, and restore
  with their shell commands turned off (`--sandbox`, `--system-command=OFF`), a client able to do neither refused;
  PostgreSQL reads its credentials from the tool's environment;
  a SQLite file name that would end a dot command is refused, a missing SQLite backup is never restored, and a
  restored SQLite copy passes `PRAGMA integrity_check`.
- `BackupKey` and `BackupCipher`: 32-byte keys in base64, files encrypted by libsodium's secret stream in chunks of
  64 KiB under a versioned header, every chunk authenticated and the last one marked final, so that another key, an
  altered, reordered, cut or extended file is refused.
- `TarWriter`, `TarReader` and `TarEntry`: POSIX ustar archives of directories and regular files written from
  scratch, long paths split into prefix and name, checksums verified, links and other entries refused.
- `MediaArchive`: named media directories archived sorted, links and special files left out, a missing directory
  archived empty; an archive verified whole before anything is written, written back over files of the same path,
  never through a link and never out of its directory; `prune()` removing what the directories hold and the archive
  does not, links unfollowed.
- `BackupDirectory` and `BackupSet`: sets named after the database, the time in UTC and an optional label, the
  database file and the media archive of the same encryption beside it; the sets and the latest set without a
  label of one encryption, a set of any file given by its
  path, temporary files owner-only in the directory; a stem already taken gets a copy number, `_2` and up, so that
  two sets of the same label within the same second never overwrite each other.
- `RotationPolicy`: the latest backups and the most recent of each of the latest days, ISO weeks and months, in
  UTC; labelled sets never rotated.
- `RestoreGuard`: a restore refused in production unless `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` holds the name of
  the database, then without its confirmation, then without the name typed again.
- `Backups`: `create()` encrypting each file on the way and removing a set left half written, `prune()`,
  `keepLatest()` bounding the sets of one label, `find()`, `restore()` reading the media archive whole before the
  database is touched, pruning the media on demand, with its `Restoration`, and `verify()` with its
  `Verification`, the restore test in a copy of the database; with a key, a set in clear refused by `find()`,
  `restore()` and `verify()` unless `$allowUnencrypted` accepts it.
- `BackupError`, `BackupFailed`, `InvalidBackupValue` and `RestoreRefused`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
