# Backup sets

## `Backups`

`Backups` puts the library together:

```php
new Backups(
    DatabaseClient $client,
    BackupDirectory $directory,
    ?BackupCipher $cipher = null,                  // null: files in clear
    MediaArchive $media = new MediaArchive([]),    // no media directories
    ?RotationPolicy $rotation = null,              // null: every set is kept
    bool $allowUnencrypted = false,                // true: with a key, sets in clear are still read
);
```

| Method | Does |
|---|---|
| `create($at, $label = '', $media = true)` | Backs the database up, and the media directories unless `$media` is false, into a new set; gives the `BackupSet` back |
| `prune()` | Removes the sets the rotation has expired, and gives them back |
| `find($file)` | The set of the database file given, wherever it is, or with null the latest set without a label |
| `keepLatest($label, $keep)` | Removes the sets of the label but the `$keep` most recent ones, and gives them back; see [restoring.md](restoring.md) |
| `restore($set, $media = true, $prune = false)` | Replaces the database's content with the set and writes its media files, pruning the others with `$prune`; gives a `Restoration` back |
| `verify($set, $scratch = null)` | The restore test; see [restore-tests.md](restore-tests.md) |

A set that fails half way — the database written, the media archive not — is removed whole: no file of a failed
backup is left to be mistaken for a good one.

## The directory

`BackupDirectory($path)` is created on first use, readable by its owner alone (`0700`). Every file of a set is
narrowed to `0600` wherever it is written. **A backup holds every person's data**: keep the directory out of version
control and out of web roots, and copy the sets off the machine with tools that keep them private.

A copy in clear exists only while a file is encrypted, decrypted or tested, as a temporary file of the directory,
`.phil-backup-` followed by random characters, created `0600` by `tempnam()` and removed whatever happens.

## Names

A set is named after the database, the time in UTC and an optional label:

```
shop-20261107-031500.dump.enc             the database
shop-20261107-031500.media.tar.enc        its media archive
shop-20261107-041000-before-restore.dump  a labelled set, in clear
```

The database part is the database's name, or the SQLite file's name without its directory and extension, with any
run of characters other than letters, digits, `-` and `_` replaced by `-`. The time is that of `create()`'s `$at`,
in UTC, so that names sort in time order whatever the server's time zone. A label is lowercase letters, digits and
`-`, starting with a letter or a digit; another is refused with `InvalidBackupValue`.

A second set created in the same second with the same label gets a copy number after an underscore, `_2`, then
`_3` and up to `BackupDirectory::COPIES` (100): no set replaces another. The copy number is not part of the label —
`shop-20261107-041000-before-restore_2` is labelled `before-restore` — and `BackupSet::$copy` holds it, 1 for the
first set. A stem is taken as soon as any file of the directory starts with it and a dot; the 101st set of one stem is
refused with `BackupFailed`.

## Finding sets

`sets($extension)` reads the sets of the directory from the names of their database files, encrypted or not, oldest
first — by time, then copy number, then stem, as `BackupSet::compare()` orders them; a file whose name does not follow the pattern, or holds an impossible date, is not a set and is never touched.
`latest($extension)` is the most recent set **without a label**: a labelled set — the copy taken before a restore, an
anonymized copy — is restored by naming its file. `labelled($extension, $label)` gives the sets of one label, oldest
first. Each takes a last argument, `$encrypted`: true reads the encrypted sets alone, false those in clear alone, null,
the default, both.

`set($file, $extension)` reads the set of any file given by its path: the media archive beside it is found by its
stem, and has the encryption of the database file — `.media.tar.enc` beside a `.enc` file, `.media.tar` beside one in
clear, never the other way round. A file named otherwise is a set of its own, timed by its modification time, without media. A file that does
not exist is refused.

`remove($set)` deletes the set's files.
