# Restoring

A restore replaces every row of the database. `Backups::restore()` does it without asking; whether it may happen is
decided before, by the `RestoreGuard`.

## The restore guard

`new RestoreGuard($environment, $productionEnvironments = ['prod'])` checks, in this order:

1. **Production.** In one of the production environments, the environment variable
   `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` must hold the name of the database being restored. A value left set from a
   restore of another database, or set to `1`, does not do: the allowance is about this database, for this run.
2. **Confirmation.** The restore is confirmed.
3. **The name typed again**, as `DatabaseIdentity::typedName()` gives it: the database's name, or the SQLite file's
   name without its directory.

`checkEnvironment($database, $allowance)` runs the first check alone, so that a console asks for the name only when
the restore may happen at all; `check($database, $allowance, $confirmed, $typed, $backup)` runs the three. Each
refusal is a `RestoreRefused` that says what to do:

```
A restore is refused in the "prod" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, shop, for this run alone.
Nothing was restored: add --confirm to replace the content of the postgresql database shop on db.internal with var/backup/shop-20261107-031500.dump.enc.
Nothing was restored: type the name of the database again, shop, with --database.
Nothing was restored: "Shop" is not the name of the database, shop.
```

## The backup taken first

Before a restore that passed the guard, back the current content up — `Backups::create()` with the label
`before-restore` — and restore only once that backup is written. A restore of the wrong set is then undone by
restoring the labelled one. `phil/backup-bundle`'s command does exactly this.

Rotation never touches a labelled set, so these copies would pile up: once a restore succeeds,
`Backups::keepLatest('before-restore', 3)` removes all but the three most recent, and gives the removed sets back.
Keep them only after a success: a restore that failed half way may have changed the database, and the copy taken
before the next attempt holds that changed content.

## The order of a restore

`restore($set, $media = true, $prune = false)`:

1. decrypts the media archive, when the set has one and media are restored, and reads it whole: a damaged archive
   stops the restore **before the database is touched**;
2. decrypts the database file into a temporary file and restores it with the platform's tool;
3. writes the media files back, as [media-files.md](media-files.md) describes;
4. with `$prune`, removes the files of the media directories that the archive does not hold.

It gives a `Restoration` back: `$mediaFiles`, the number of media files written, and `$removedFiles`, the number
removed by pruning — both zero for a restore of the database alone.

A restore of the database alone — `$media = false`, or a set without media — leaves the media directories as they
are.
