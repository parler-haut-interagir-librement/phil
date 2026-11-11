# Media files

## Named directories

`MediaArchive` takes the media directories of the application, each by a name:

```php
new MediaArchive([
    'uploads' => '/srv/shop/public/uploads',
    'invoices' => '/srv/shop/var/invoices',
]);
```

A name is letters, digits, `-` and `_`; another is refused with `InvalidBackupValue`. The archive holds each
directory's files under its name — `uploads/2026/11/poster.jpg` — and never its path, so a backup taken on one
machine restores on another whose directories are elsewhere, provided the names are the same.

## What is archived

`write($file)` writes one POSIX ustar archive, readable by its owner alone, and gives the number of files back:

- every directory and regular file under each directory, sorted by path, so that the same files give the same
  archive, with their permission bits and modification times;
- **no symbolic link**, which could point anywhere on the machine, and no socket, pipe or device;
- a directory that does not exist yet, as an empty directory: a fresh installation without uploads backs up.

A path longer than 100 bytes is split at a slash into the archive's 155-byte prefix and its name; one that cannot
be split, or a file of 8 GiB or more, is refused, as is a file whose size changes while it is archived. A directory
that cannot be read whole is named in the error.

## Writing back

`extract($file)` first reads the whole archive — every header checksum, every path — and refuses it, before writing
anything, when a path names a directory the archive was not given, or holds an empty, `.` or `..` segment. Then each
file is written to its directory:

- over the file of the same path, with the permission bits and modification time it was archived with;
- a symbolic link in the way of a file is removed, never written through;
- a directory that leads out of its media directory through a link is refused;
- missing directories are created.

Files the archive does not hold are left: a restore brings back what was backed up, and leaves what was added since.

`prune($file)` removes them, after `extract()`, so that each directory is left as it was backed up. It reads the
archive whole first — a path naming no configured directory is refused before anything is removed — then, in each
directory the archive names, removes every file, symbolic link and special file the archive does not hold, the link
itself and never what it points to, and the directories left empty, deepest first. A directory the archive holds is
kept even when empty, and a configured directory the archive does not name is left whole. It gives the number of
entries removed back, directories aside. Pruning is off unless asked for: a file uploaded after the backup is gone
once it runs, so take a backup of the current content first.

`verify($file)` reads the archive whole, as the first step of `extract()`, without writing anything, and gives the
number of files back.

## The tar format

`TarWriter` and `TarReader` are written from the format's description: one 512-byte header per entry, the content
padded to 512 bytes, two empty blocks at the end. The reader accepts directories and regular files — a NUL type is a
regular file, as in old archives — and refuses links and every other kind of entry, an archive without its end
block, a header whose checksum or `ustar` magic is wrong, and an archive cut short. Content left unread by the
reader's visitor is skipped without being read.
