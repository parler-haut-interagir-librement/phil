# Restore protection

`phil:backup:restore` replaces every row of the database. Before anything is changed, it passes three checks, in this
order, then backs the current content up.

## 1. Production

In one of `restore.production_environments` (`[prod]`), a restore is refused unless the environment variable
`PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` holds **the name of the database being restored**:

```bash
PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE=shop bin/console phil:backup:restore var/backup/shop-20261107-031500.dump.enc --confirm --database=shop
```

The variable is read from the process's environment, or from where Symfony's Dotenv writes it. It is never read from
the configuration, so that it cannot be left on by a deployment, and a value left set from a restore of another
database, or set to `1`, does not do. Set it for the one run that needs it, as above, and nowhere else.

## 2. Confirmation

Without `--confirm`, the command says what it would replace with what, and stops.

## 3. The name typed again

The database's name — the file name without its directory for SQLite, `app.sqlite` — is typed again, with
`--database`, or at the prompt of an interactive console:

```
 The postgresql database shop on db.internal will be replaced with var/backup/shop-20261107-031500.dump.enc. Type its name, shop, to go on:
 > shop
```

A name that differs, even by its case, is refused. A console that is not interactive and gives no `--database` is
refused.

## The backup taken first

Once the three checks pass, the current content — the database, and the media unless `--no-media` is given — is
backed up into a set labelled `before-restore`, encrypted like every other and never rotated. The restore starts only
once that set is written; a restore of the wrong set is undone by restoring it:

```bash
bin/console phil:backup:restore var/backup/shop-20261107-104512-before-restore.dump.enc --confirm --database=shop
```

Once a restore succeeds, the oldest sets labelled `before-restore` are removed, so that the latest three —
`restore.keep_before_restore` — remain; a failed restore removes none. Two restores within the same second keep
both sets, the second named with a copy number: `shop-20261107-104512-before-restore_2.dump.enc`.
