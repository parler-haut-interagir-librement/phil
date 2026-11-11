# Extension points

## `CommandRunner`

The library never starts a process itself. A `CommandRunner` given to each client does:

| Method | Contract |
|---|---|
| `find($candidates)` | The path of the first installed tool of the list; `BackupFailed::missing($candidates, $remedy)` when none is. |
| `run($command, $environment = [], $input = null)` | Runs the command — a list of arguments, never through a shell — with these environment variables added, and the file as its standard input when given; gives its standard output back; `BackupFailed::failed($tool, $errorOutput)` when it exits with an error. |

`phil/backup-bundle` has one over Symfony Process, without a time limit unless configured. A runner of your own
must pass each argument as it is, give the environment to the tool alone, and keep no command line in a log: it may
name an option file, never a password.

## `DatabaseClient`

Another database, or another way of copying one — a managed service's snapshot API, say — is a `DatabaseClient`:

| Method | Contract |
|---|---|
| `identity()` | The platform, host and name; messages, file names and the restore guard read them. |
| `extension()` | The extension of a backup file, without its dot. |
| `backup($file)` | A complete, consistent backup in the file; `BackupFailed` when it cannot be made. |
| `restore($file)` | The database's content replaced by the backup, whole or not at all where the platform allows; `BackupFailed` when the file is missing or it cannot be restored. |
| `withDatabase($name)` | The same client for another database of the same server. |
| `check()` | Checks a restored copy beyond the tool's success, or does nothing; `BackupFailed` when the copy fails. |

A client must keep credentials off command lines, and must refuse a restore from a file that does not exist rather
than empty the database.

## Building on `Backups`

Every part of `Backups` can be used alone: `BackupCipher` for any file, `MediaArchive` for any set of directories,
`RotationPolicy` over any list of `BackupSet`s, `RestoreGuard` in front of any restore.
