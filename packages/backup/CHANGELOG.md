# Changelog

All notable changes to `phil/backup` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.4-alpha - 2026-11-24

### Added

- The [rotation guide](doc/rotation.md#labelled-sets-and-their-retention) gives labelled sets a retention period, by
  count or by age, and says that a copy labelled `anonymized` is still personal information; it also says that
  `Backups` takes no lock, and when an application needs one.

### Changed

- `MediaArchive::verify()`, and so `extract()` and `Backups::verify()`, refuse an archive that holds a path twice —
  two files, two directories, a file and a directory of the same place, or one place under two media names —, which
  `TarWriter` never writes and a restore would settle by keeping whichever came last.

## 0.1.3-alpha - 2026-11-20

Restoring a tar archive never creates a directory outside its media directory.

### Changed

- `MediaArchive::verify()`, `extract()` and `prune()` refuse an archive path holding a backslash, or a segment that
  opens with a drive letter, as one holding a `..` segment: neither names the same place on every system.
- `MediaArchive::extract()` checks that the nearest directory that exists above a missing one lies inside its media
  directory, through its links, before creating anything: a link out of the media directory no longer receives the
  empty directories of the archive before the path is refused.

## 0.1.2-alpha - 2026-11-18

Every file of a backup is readable by its owner alone from the moment it exists, and
an open backup directory is narrowed to its owner. See [The directory](doc/backup-sets.md#the-directory).

### Added

- `BackupDirectory`'s argument `tightenOpenDirectory`, true by default: false refuses a directory open to its group or
  to other users instead of narrowing it.
- `BackupCipher`'s argument `tightenOpenDirectory`, true by default: false refuses a target directory open to its group
  or to other users instead of narrowing it.
- `BackupDirectory::publish()`: a temporary file of the directory takes its final name in one step, by a hard link,
  never over an existing file, or by a rename on a file system without hard links.
- `TarWriter::discard()`: the archive given up, its temporary file removed; a writer neither closed nor discarded
  removes it when it is destroyed.

### Changed

- `BackupDirectory` narrows an existing directory open to its group or to other users to `0700` before anything is
  written in it, and refuses it, with a `BackupFailed` naming the directory and its mode, when it cannot be narrowed.
  Reading sets never changes the mode.
- `Backups::create()` writes each file of a set, encrypted or in clear, as a temporary file of the directory created
  `0600`, then gives it its name; a set that fails removes only its own files, never a file another backup gave the
  same name.
- `TarWriter::create()` and `MediaArchive::write()` write the archive as a temporary file beside its path, created
  `0600`, which takes the path when the archive is closed; a failure leaves the path as it was.
- `BackupCipher::encrypt()` and `decrypt()` write the target as a temporary file of its directory, created `0600`,
  then rename it to the target, replacing a file of that name; a failure leaves the target as it was and removes the
  temporary file. A missing directory is created `0700`; an existing one open to its group or to other users is
  narrowed to `0700`, or refused with a `BackupFailed` naming it and its mode when it cannot be.
- `BackupDirectory::temporary()` creates its file in the directory a symbolic link points to, and removes the file
  `tempnam()` creates in the system's temporary directory when it cannot write in the backup directory.

### Security

- A backup in clear, or a media archive, was written at its final path with the default mode, then narrowed to
  `0600`: another user of the machine could read it while it was written, from a directory open to them. No byte of
  a backup is readable by another user at any moment.
- `BackupCipher` opened its target with the default mode, then narrowed it to `0600`, and a decryption that failed
  left the authenticated beginning of the file at the target: a copy in clear or encrypted written outside a backup
  directory could be read by another user while it was written. The target is now readable by its owner alone from
  the moment it exists, and receives the copy whole or not at all.

## 0.1.1-alpha - 2026-11-14

### Fixed

- `TarReader`: the end of an archive is two empty blocks; an archive cut after its first empty block, or one whose
  empty block is followed by anything but a second, is refused as damaged by `MediaArchive::verify()` and
  `extract()` instead of being read as complete. NUL padding after the end block, up to the 10 240-byte record, is
  ignored; any other byte after it is refused.

### Changed

- `MediaArchive::write()` walks the media directories one directory at a time as it writes the archive, instead of
  holding every path of the tree in memory: each directory is followed by its entries sorted by name, in byte order.
  An archive of the same files stays the same from one backup to the next; next to the order by whole path of
  0.1.0-alpha, only sibling names that extend another with a byte below `/`, such as `a` and `a-b`, are ordered
  differently.
- `TarReader` computes each header's checksum by counting its byte values instead of building an array of one
  element per byte: reading an archive of 100,000 small entries takes about a third of the time.

## 0.1.0-alpha - 2026-11-11

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
- `TarWriter`, `TarReader` and `TarEntry`: POSIX ustar archives of directories and regular files, long paths split into
  prefix and name, checksums verified, links and other entries refused.
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
