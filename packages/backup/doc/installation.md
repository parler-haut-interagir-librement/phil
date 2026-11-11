# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `new` without parentheses |
| `ext-sodium` | The secret stream that encrypts backups, and the constant-time base64 of keys |

The client tools of the database must be installed where the backups run:

| Platform | Backup | Restore | System package, for example |
|---|---|---|---|
| SQLite | `sqlite3` | `sqlite3` | `sqlite3` |
| MySQL | `mysqldump` | `mysql` | `mysql-client` |
| MariaDB | `mariadb-dump`, or `mysqldump` | `mariadb`, or `mysql` | `mariadb-client` |
| PostgreSQL | `pg_dump` | `pg_restore` | `postgresql-client`, of the server's major version or later |

## Installing

```sh
composer require phil/backup
```

It is a library, not a bundle: there is nothing to register. `phil/backup-bundle` builds every object below from a
Symfony configuration; elsewhere, build a `Backups` once with its client, directory, cipher, media and rotation, and a
`CommandRunner` that runs processes — see [extension-points.md](extension-points.md).
