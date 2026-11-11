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

## Scheduling

The bundle schedules nothing. With Symfony Scheduler, or with cron:

```
15 3 * * *  cd /srv/shop && bin/console phil:backup:create --quiet && bin/console phil:backup:verify --quiet
```

Copy the directory off the machine after each backup — the sets are encrypted, the key stays behind — and alert on a
non-zero exit.

## A copy of production to work on

```bash
bin/console phil:backup:create                                                 # in production
# copy the set to the other machine, then there:
bin/console phil:backup:restore /tmp/shop-20261107-031500.dump.enc --confirm --database=shop_dev
```

The local content is backed up first, as everywhere. A copy that holds people's data is anonymized before it is
shared; `phil/privacy-bundle` does that, and its documentation gives the whole sequence.
