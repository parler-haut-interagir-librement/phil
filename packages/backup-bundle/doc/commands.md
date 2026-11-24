# Commands

## `phil:backup:create`

```bash
bin/console phil:backup:create
bin/console phil:backup:create --label=before-migration --no-media
```

| Option | Effect |
|---|---|
| `--label=LABEL` | Names the set `<database>-<time>-<label>` and keeps it apart from rotation; lowercase letters, digits and `-` |
| `--no-media` | Backs the database up alone |
| `--no-rotate` | Removes no expired set this time |

It writes a new set to the directory — the database file and, with media directories, the media archive, each
encrypted when a key is configured — then removes the sets the rotation has expired:

```
The postgresql database shop on db.internal is backed up in /srv/shop/var/backup/shop-20261107-031500.dump.enc.
The media files are archived in /srv/shop/var/backup/shop-20261107-031500.media.tar.enc.
The expired backup shop-20261031-031500 is removed.
```

A failure — a missing tool, a database that refuses the connection, a directory that cannot be written — is printed
and the command exits with `1`; no file of the failed set is left.

## `phil:backup:restore`

```bash
bin/console phil:backup:restore --confirm
bin/console phil:backup:restore var/backup/shop-20261107-031500.dump.enc --confirm --database=shop
```

| Argument or option | Effect |
|---|---|
| `file` | The database file of the set; by default the most recent set of the directory without a label |
| `--confirm` | Restore; without it, the command only says what it would replace with what |
| `--database=NAME` | The database's name typed again; asked for when the console is interactive |
| `--no-media` | Restores the database alone |
| `--prune` | Removes the media files the set does not hold, once its files are written; off by default |

The [restore protection](restore-protection.md) runs first; each refusal exits with `2` and changes nothing. Then:

```
The current content is backed up in /srv/shop/var/backup/shop-20261107-104512-before-restore.dump.enc first.
The postgresql database shop on db.internal is restored from var/backup/shop-20261107-031500.dump.enc.
1284 media file(s) are restored from var/backup/shop-20261107-031500.media.tar.enc.
```

With `--prune`, the media directories are then left as they were backed up — the files uploaded since are removed,
and are found in the set taken first:

```
12 media file(s) the backup does not hold are removed.
```

Once the restore succeeds, the sets labelled `before-restore` are removed but the latest ones,
`restore.keep_before_restore` of them, three by default, each named:

```
The older backup shop-20261101-091500-before-restore is removed.
```

A failure after the guard exits with `1`. A failure of the backup taken first stops the command before the restore.

## `phil:backup:verify`

```bash
bin/console phil:backup:verify
bin/console phil:backup:verify var/backup/shop-20261107-031500.dump.enc
```

It tests a set — the file given, or the most recent set without a label — without touching the database:

```
The files of shop-20261107-031500 decrypt and authenticate.
The database of shop-20261107-031500 restores: it was restored into the postgresql database shop_restore_test on db.internal and checked.
The media archive of shop-20261107-031500 reads whole: 1284 file(s).
```

A SQLite backup is restored into a temporary file and passes `PRAGMA integrity_check`. A server's backup is
restored into `verify.database`; without one, the command says so and tests the rest. A failure exits with `1`. See
phil/backup's restore tests (`doc/restore-tests.md` of that package).

## `phil:backup:monitor`

```bash
bin/console phil:backup:monitor
bin/console phil:backup:monitor --max-age=50
```

| Option | Effect |
|---|---|
| `--max-age=HOURS` | Hours the most recent backup may be old at most, a whole number, 1 or more; by default `monitor.max_age_hours`, 26 |

It says whether the most recent set without a label — the one `phil:backup:restore` would take — was taken less than
that many hours ago, by the time in its name:

```
The most recent backup, shop-20261107-031500, was taken 7 h 42 min ago: less than 26 hour(s).
```

It exits with `0` when the backup is fresh, `1` when it is older or there is none, and `2` for a maximum age that is
not a whole number of hours. With a key, a set in clear is never counted. It only lists the directory: it takes no
lock and opens no set, so it runs as often as the monitoring wants, even during a backup. Whether the set restores is
`phil:backup:verify`'s question.

## The lock

`phil:backup:create`, `phil:backup:restore` and `phil:backup:verify` hold one exclusive lock per backup directory,
through Symfony Lock, from the start of their work to its end: the backup and the rotation after it; the copy taken
before a restore, the restore and the removal of the older copies; the restore test. A rotation therefore never
removes a set that another command is writing or testing, and two schedules that overlap never run together. None of
them waits for the lock: a command that finds it taken fails at once, changes nothing, and exits with `1`:

```
Another backup, rotation, restore or restore test of /srv/shop/var/backup is running: try again once it ends.
```

The restore takes the lock once the [restore protection](restore-protection.md) has let it through, so a question
waiting for the database's name never blocks a backup. By default the lock lives in a flock store in the system
temporary directory, which serves the commands of one machine and is freed when a process ends, however it ends.
Several machines that write to the same directory share a store through `lock.factory`; see
[Configuration](configuration.md).

## Scheduling

The bundle schedules nothing. With Symfony Scheduler, or with cron:

```
15 3 * * *  cd /srv/shop && bin/console phil:backup:create --quiet && bin/console phil:backup:verify --quiet
*/30 * * * *  cd /srv/shop && bin/console phil:backup:monitor --quiet
```

Copy the directory off the machine after each backup — the sets are encrypted, the key stays behind — and alert on a
non-zero exit, of the backup and of `phil:backup:monitor`, which also catches a backup that never started.

## A copy of production to work on

```bash
bin/console phil:backup:create                                                 # in production
# copy the set to the other machine, then there:
bin/console phil:backup:restore /tmp/shop-20261107-031500.dump.enc --confirm --database=shop_dev
```

The local content is backed up first, as everywhere. A copy that holds people's data is anonymized before it is
shared; `phil/privacy-bundle` does that, and its documentation gives the whole sequence.

### How long a copy labelled `anonymized` is kept

A copy anonymized this way, then backed up with `--label=anonymized`, **still holds personal information**: its
primary keys, the columns no rule rewrites and its free text tie it back to production. Under Quebec's law on the
protection of personal information in the private sector it is at best de-identified, not anonymized, and other laws
see it the same way. It needs a retention period of its own, like any personal information, and its leak is a
confidentiality incident.

Rotation never removes a labelled set, so such a copy stays until it is removed. Set its period in one of two ways:

- **by count**, after each new copy: `Backups::keepLatest('anonymized', 1)` removes every copy but the latest;
- **by age**, on a schedule: remove the copies older than the period, as phil/backup's rotation guide shows
  (`doc/rotation.md` of that package, *Labelled sets and their retention*), or with a retention rule of
  `phil/privacy-bundle` that calls it.

Write the period down beside the other retention periods of the application, and remove the restored working
database itself once the work is done: it is the same information, outside the backup directory.
