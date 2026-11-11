# Restore tests

`Backups::verify($set, $scratch = null)` proves what can be proved of a set without touching the database, and
gives a `Verification` back.

## What a test does

1. Every encrypted file is decrypted into a temporary file, which authenticates it whole.
2. The database is restored into a copy and checked there:
   - **SQLite**: into a temporary file of the backup directory, then `PRAGMA integrity_check`; the copy is removed;
   - **MySQL, MariaDB, PostgreSQL**: into the scratch database `$scratch`, on the same server and with the same
     credentials; the restore tool's success is the check. Without a scratch database, the database is not
     restored, and `restoredInto` is null.
3. The media archive is read whole, every header and path checked; `mediaFiles` is its number of files, null when the
   set has none.

A failure at any step is a `BackupFailed` naming the file and the cause.

## The scratch database

The scratch database is one the application gives up to the tests: each test replaces its content. Create it once,
empty, with the rights the backup's objects need — for PostgreSQL, owned by the role the client connects as. A
scratch database with the same name as the database itself is refused: `The restore test would replace the
postgresql database shop itself: give it a scratch database of its own.`

## How often

A restore test of the newest set after each backup costs one restore. Schedule it after the backup; an alert on its
failure says a backup exists that would not have restored — the day it is found, rather than the day it is needed.
