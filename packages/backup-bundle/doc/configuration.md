# Configuration

Every key of `phil_backup`, with its default:

```yaml
phil_backup:
    connection: database_connection             # a Doctrine DBAL connection service
    directory: '%kernel.project_dir%/var/backup'
    binaries: {}                                # { pg_dump: /usr/lib/postgresql/17/bin/pg_dump }
    timeout: null                               # seconds; null, no limit
    key: null                                   # '%env(PHIL_BACKUP_KEY)%'; required unless allow_unencrypted
    allow_unencrypted: false                    # true, backups without a key are written in clear; with a key, older ones in clear are read
    media: {}                                   # { uploads: '%kernel.project_dir%/public/uploads' }
    rotation: { last: 0, daily: 0, weekly: 0, monthly: 0 }
    restore:
        production_environments: [prod]
        keep_before_restore: 3
    verify:
        database: null                          # the scratch database of a server's restore test
```

| Key | Type | Default | Effect |
|---|---|---|---|
| `connection` | non-empty string | `database_connection` | Service id of the Doctrine DBAL connection backed up and restored; its platform chooses the client, its parameters give the credentials. |
| `directory` | non-empty string | `%kernel.project_dir%/var/backup` | Where sets are written and found, created `0700`. |
| `binaries` | map of tool name to path | `{}` | Paths of client tools that are not on the `PATH`. |
| `timeout` | float or null | `null` | Seconds a client tool may run. |
| `key` | string or null | `null` | The backup key, 32 random bytes in base64; read when a command first needs it, so an environment variable works. Required unless `allow_unencrypted` is true. |
| `allow_unencrypted` | boolean | `false` | Whether backups may be written in clear when `key` is null. A key, when given, is always used, and a backup in clear is then refused by `phil:backup:restore` and `phil:backup:verify` — anyone able to write to the backup storage could have put it there — unless this is true, for the backups written before the key. |
| `media` | map of name to directory | `{}` | Media directories backed up beside the database, each by a name of letters, digits, `-` and `_`. |
| `rotation.last`, `daily`, `weekly`, `monthly` | integers, 0 or more | `0` | How many sets each rule keeps after each `phil:backup:create`; all zero, every set is kept. See phil/backup's rotation (`doc/rotation.md` of that package). |
| `restore.production_environments` | list of strings | `[prod]` | Kernel environments in which a restore needs `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE`; see [restore-protection.md](restore-protection.md). |
| `restore.keep_before_restore` | integer, 1 or more | `3` | How many sets labelled `before-restore` are kept once a restore succeeds; the older ones are removed. |
| `verify.database` | string or null | `null` | The scratch database, on the same server, into which `phil:backup:verify` restores a MySQL, MariaDB or PostgreSQL backup; `null`, it is not restored. |

## Services

| Service id | Class | Visibility | Definition |
|---|---|---|---|
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | — | private | From `binaries` and `timeout`. |
| `phil_backup.database_client` | `DatabaseClient` | private | Built by `DatabaseClients::forConnection()` from `connection` and the runner (`PhilBackupBundle::DATABASE_CLIENT`). |
| `Phil\Backup\Storage\BackupDirectory`, `Phil\Backup\Media\MediaArchive` | — | private | From `directory` and `media`. |
| `Phil\Backup\Encryption\BackupKey`, `BackupCipher` | — | private | Only with a `key`: `BackupKey::fromBase64()` of the key, and the cipher over it. |
| `Phil\Backup\Rotation\RotationPolicy` | — | private | Only when a rule is above zero. |
| `Phil\Backup\Restore\RestoreGuard` | — | private | From `%kernel.environment%` and `restore.production_environments`. |
| `Phil\Backup\Backups` | — | public | Over the client, the directory, the cipher or null, the media archive and the rotation or null. |
| `CreateBackupCommand`, `RestoreBackupCommand`, `VerifyBackupCommand` | — | private | `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify`; the restore with `restore.keep_before_restore`, the last with `verify.database`. |

Nothing connects to the database while the container is built or the client created: the platform comes from the
connection's parameters, or from the server version they give.

## Build-time validation

The Config component refuses an empty `connection` or `directory`, an empty media directory, a negative rotation
count and a `keep_before_restore` below 1. A backup holds every person's data, so a configuration without a `key`
is refused unless it says otherwise:

```
Invalid configuration for path "phil_backup": Backups are encrypted: give phil_backup.key, or set phil_backup.allow_unencrypted to true to write them in clear.
```
 The extension builds the media archive once while loading, so a bad media name fails the container build
rather than the first backup:

```
phil_backup.media: The media name "up loads" holds characters other than letters, digits, "-" and "_".
```

A key that is not 32 bytes of base64 is refused when a command first builds `Backups`, with
`A backup key is 32 random bytes in base64, such as the output of base64_encode(random_bytes(32)).`
