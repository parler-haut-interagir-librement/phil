# Testing

## The library's tests

The suite `tests/Unit` tests:

| Area | What is tested |
|---|---|
| `Database` | the command lines of MySQL, MariaDB and PostgreSQL recorded without a server (fixtures `RecordingRunner`, `FailingRunner`), with the option file's content and mode and its removal after a failure; the SQLite commands, refused file names, the integrity check; identities |
| `Encryption` | sizes around the chunk boundary round trip with the exact layout; another key, a flipped byte, cut, swapped, extended files refused; keys |
| `Media` | archives read back as written, long paths, damaged headers, links and devices refused, files that change or are too large (fixture `LyingFile`); named directories archived and written back elsewhere, links, sockets, unsafe paths and unreadable directories; pruning of files, links and emptied directories, a directory the archive does not name left |
| `Storage`, `Rotation`, `Restore` | names, UTC, labels, copy numbers up to their bound, parsing and sorting; each rule of the rotation on known dates; each refusal of the guard |
| `Backups` | encrypted and clear sets with the real `sqlite3` (fixture `ProcessRunner`, on Symfony Process): creation, cleanup after a failure, rotation, the latest sets of a label kept, restore with and without pruning, the media archive read before the database, restore tests on SQLite and on a recorded MySQL client |

From the monorepo root:

```sh
vendor/bin/phpunit packages/backup/tests
```

The SQLite tests need the `sqlite3` command line tool on the `PATH`. The checks that count on a file or a directory
being unreadable or unwritable are skipped when run as root. The library is held to 100 % covered mutation score by Infection.

## Testing code that backs up

Give the clients a recording `CommandRunner` to assert on the command lines without a server, and run the SQLite
client on a temporary file with a real runner to test a whole backup and restore. Compare times by their timestamps
or their formatted strings, never with `==`.
