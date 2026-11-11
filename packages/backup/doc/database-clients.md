# Database clients

A `DatabaseClient` backs up and restores one database with its platform's own tools. The library has one per
platform; each takes a `CommandRunner` that finds and runs the tools, and never builds a shell command.

## Command lines

| Client | Backup | Restore | File |
|---|---|---|---|
| `SqliteClient($path, $runner)` | `sqlite3 <path> ".backup '<file>'"` | `sqlite3 <path> ".restore '<file>'"` | `.sqlite` |
| `MysqlClient($database, $connection, $runner, false)` | `mysqldump --defaults-file=<options> --single-transaction --routines --triggers --result-file=<file> <database>` | `mysql --defaults-file=<options> --system-command=OFF <database> < <file>` | `.sql` |
| `MysqlClient($database, $connection, $runner, true)` | `mariadb-dump`, or `mysqldump` when it is missing, with the same options | `mariadb`, or `mysql`, with `--sandbox` | `.sql` |
| `PostgresqlClient($database, $connection, $runner)` | `pg_dump --format=custom --file=<file> --dbname=<database>` | `pg_restore --clean --if-exists --no-owner --single-transaction --dbname=<database> <file>` | `.dump` |

`.backup` writes a consistent copy of a SQLite file even while the application writes to it. The MySQL dump is one
transaction, with routines and triggers, and names no database, so it restores into whichever database the client is
given. The PostgreSQL custom format is compressed; its restore drops each object before recreating it, leaves
ownership to the connecting role, and runs in one transaction: a backup restores whole or not at all.

## Credentials

`$connection` holds `host`, `port`, `user`, `password` and, for MySQL and MariaDB, `unix_socket`, as strings; a
missing key is left to the tool's default.

**MySQL and MariaDB** read them from an option file created by `tempnam()` in the system's temporary directory —
readable by its owner alone — given with `--defaults-file`, and removed once the tool has run, even when it
fails. That file is the only one the tool reads: a `~/.my.cnf` left on the server adds no option. **PostgreSQL** reads them from `PGHOST`, `PGPORT`, `PGUSER` and `PGPASSWORD`, given to the tool's process
alone. Neither puts a password on a command line, where `ps` shows it to every user of the machine. SQLite has none.

## Shell commands in a MySQL dump

The `mysql` and `mariadb` clients run the `\!` and `system` lines of their input on the machine, even in batch mode:
a dump planted in the backup storage, or handed over to be tested, would run any command with the application's
rights. A restore asks the client for its `--help` first and turns those commands off: `--sandbox` when the client
lists it (MariaDB 10.5.25, 10.6.18, 10.11.8, 11.0.6, 11.1.5, 11.2.4, 11.4.2 and later), else `--system-command=OFF`
(MySQL 8.0.40, 8.4.3, 9.1.0 and later). A client that offers neither is refused before it reads the dump, with
`… cannot turn its shell commands off, which a dump could run on this machine: install MySQL 8.0.40 or later, or
MariaDB 10.5.25 or later.`

## SQLite file names

`sqlite3` reads the file of `.backup` and `.restore` between single quotes. A name that holds a quote or a control
character, which would end the argument or the command, is refused with `BackupFailed`. A restore from a file that
does not exist is refused before `sqlite3` runs: `.restore` would create the file empty and empty the database with
it.

## Another database of the same server

`withDatabase($name)` gives the same client for another database of the same server, with the same credentials — or
another file, for SQLite. A restore test restores there; see [restore-tests.md](restore-tests.md).

## Checks

`check()` runs after a restore into a copy. `SqliteClient` runs `PRAGMA integrity_check` and fails unless the answer
is `ok`. The MySQL and PostgreSQL clients check nothing more: the restore tool's success, reading the whole dump, is the
check.

## Identity

`identity()` gives a `DatabaseIdentity`: the platform (`sqlite`, `mysql`, `mariadb`, `postgresql`), the host or null,
and the name — the file, for SQLite. `describe()` writes it for messages, `postgresql database shop on db.internal`;
`typedName()` is the name a person types again to confirm a restore, the file name without its directory for SQLite.

## Failures

A tool that is missing is named, with what to do: `pg_dump is not installed or not on the PATH: install your
database's client tools, or give the path of the tool.` A tool that fails is reported with its error output,
`pg_dump failed: role "x" does not exist`. Both are `BackupFailed`.
