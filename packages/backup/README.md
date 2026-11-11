# phil/backup

**Back a database and its media files up, encrypted and rotated, restore them behind a guard, and prove that they restore.**

phil/backup copies a database to a file with its platform's own client tools — `sqlite3`, `mysqldump` or
`mariadb-dump`, `pg_dump` — and back with `sqlite3`, `mysql` or `mariadb`, `pg_restore`. Credentials never reach a
command line, where every user of the machine reads them: MySQL and MariaDB read them from an option file readable by
its owner alone, PostgreSQL from the tool's environment. The media directories of the application — uploads, generated
images, documents — go beside the database in a tar archive, each under a name rather than a path, so that a backup
restores on a machine whose directories are elsewhere.

Every file of a backup can be encrypted with a 32-byte key by libsodium's secret stream, chunk by chunk, so that a
backup of any size passes through a fixed amount of memory and an altered, cut or swapped file is refused. Backups are
named after the database and the time in UTC, and a grandfather-father-son rotation keeps the latest ones and the most
recent of each day, week and month. A restore guard refuses a restore in production unless an environment variable
names the database, and asks for a confirmation and the database's name typed again. A restore test restores a backup
into a copy — a temporary file for SQLite, a scratch database for a server — and checks it there, without touching
the database.

It exists because a backup nobody has restored is a hope, not a backup, and because a backup holds every person's data
in clear: it is kept to its owner, encrypted where it is stored, and replaced on a schedule. It is for any PHP
application that keeps its data in SQLite, MySQL, MariaDB or PostgreSQL; `phil/backup-bundle` puts it in Symfony, with
its configuration and its commands.

It is a plain PHP library, written from scratch, whose only requirement is `ext-sodium`. It does not run processes
itself: a `CommandRunner` given by the application finds and runs the tools. It does not schedule backups, copy them
off the machine, or decide when a restore is wise: the application's scheduler, storage and people do.

## Status

| Part | State |
|---|---|
| `SqliteClient`, `MysqlClient`, `PostgresqlClient` over the client tools, credentials off the command line | Implemented and tested: SQLite with the real `sqlite3`, the others by their command lines |
| `BackupCipher` and `BackupKey`: libsodium's secret stream by chunks, a versioned format | Implemented and tested |
| `TarWriter`, `TarReader`, `MediaArchive`: ustar archives of named directories, without links | Implemented and tested |
| `BackupDirectory`, `BackupSet`: sets named after the database, the time in UTC and a label | Implemented and tested |
| `RotationPolicy`: the latest backups and the most recent of each day, ISO week and month | Implemented and tested |
| `RestoreGuard`: production, confirmation, the name typed again | Implemented and tested |
| `Backups`: create, rotate, find, restore and test a set | Implemented and tested |

## Documentation

- [Installation](doc/installation.md) — requirements, installing, the client tools of each platform.
- [Database clients](doc/database-clients.md) — the command line of each platform, credentials, restore into another
  database, checks, and the command runner.
- [Backup sets](doc/backup-sets.md) — `Backups`, the directory, the names of the files, labels, finding and removing
  a set.
- [Encryption](doc/encryption.md) — keys, the file format, what is authenticated, and what a lost key means.
- [Media files](doc/media-files.md) — named directories, what is archived and what is left, how files are written
  back.
- [Rotation](doc/rotation.md) — the rules, how periods are counted, and labelled sets.
- [Restoring](doc/restoring.md) — the restore guard, the order of a restore, and the backup to take first.
- [Restore tests](doc/restore-tests.md) — what a test proves for each platform, and the scratch database.
- [Extension points](doc/extension-points.md) — the `DatabaseClient` and `CommandRunner` ports and their contracts.
- [Testing](doc/testing.md) — the library's tests and mutation bar, and how to test code that backs up.
- [API reference](doc/reference.md) — every type of the library, with its role.

## Quick start

```sh
composer require phil/backup
```

```php
use Phil\Backup\Backups;
use Phil\Backup\Database\PostgresqlClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;

$backups = new Backups(
    new PostgresqlClient('shop', ['host' => 'db.internal', 'user' => 'shop', 'password' => $password], $runner),
    new BackupDirectory('/var/backups/shop'),
    new BackupCipher(BackupKey::fromBase64($key)),
    new MediaArchive(['uploads' => '/srv/shop/public/uploads']),
    new RotationPolicy(last: 3, daily: 7, weekly: 4, monthly: 12),
);

$set = $backups->create(new DateTimeImmutable());   // shop-20261107-031500.dump.enc and .media.tar.enc
$backups->prune();                                  // the sets the rotation has expired
$backups->verify($set, 'shop_restore_test');        // restored into the scratch database and checked
```

## Requirements

PHP 8.5 or later and `ext-sodium`; the client tools of the database on the machine that runs the backups.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
