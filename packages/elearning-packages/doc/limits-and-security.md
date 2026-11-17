# Limits and security

A package is written by someone else, on another platform, and may be crafted to harm the server that reads it. The
library reads it as hostile input: every reading is bounded, every path checked, and nothing in the package is
extracted, fetched or run.

## The limits

`PackageLimits` holds the bounds of a reading. Every bound is a positive number; `InvalidLimits` is thrown otherwise.

| Argument | Default | Bounds |
|---|---|---|
| `archiveBytes` | 512 MiB | The size of the archive file |
| `entries` | 10 000 | The entries of the archive, directories included |
| `entryBytes` | 256 MiB | The size of one file once uncompressed |
| `totalBytes` | 2 GiB | The size of every file once uncompressed: announced by the entries, and read |
| `ratio` | 200 | How many times a file larger than 1 MiB may be compressed |
| `manifestBytes` | 4 MiB | The size of `imsmanifest.xml` or `cmi5.xml` |
| `depth` | 16 | How deep the items of a SCORM organization or the blocks of a cmi5 structure nest |
| `items` | 5 000 | The items, the resources, or the blocks and units a manifest describes |

```php
use Phil\ElearningPackages\PackageLimits;

$limits = new PackageLimits(archiveBytes: 50 * 1024 * 1024, entries: 2_000, depth: 8);
```

The constants of `PackageLimits` hold the defaults, for a configuration that shows them.

## The archive

`ZipPackage::open()` checks the whole archive before any file is read, and refuses it with `PackageRefused`:

| Check | Refused |
|---|---|
| The file | Missing, unreadable, larger than `archiveBytes`, or not a ZIP archive |
| Number of entries | Past `entries` |
| Each entry's name | Not a path `PackagePath` accepts; see below |
| Names that differ only by case | The second: on a disk that ignores case, it would overwrite the first |
| Symbolic links | Any entry whose Unix mode is a link |
| Encrypted entries | Any |
| Size announced by an entry, and their sum | Past `entryBytes`, or `totalBytes` |
| Compression ratio of an entry larger than 1 MiB | Past `ratio`: a compression bomb |

A directory entry is checked for its name and otherwise left out: `files()` lists files only.

`read()` then reads a file through a stream that stops one byte past what its entry announced, so that an archive
whose headers lie is refused rather than believed. A file whose size is not the announced one, whose data ends early,
or whose CRC-32 does not match is refused, and the bytes read across the archive count against `totalBytes` again.
A warning the zip extension raises on a corrupt entry is turned into the refusal, never left to the error handler of
the application. After `close()`, every read is refused.

## Paths

`PackagePath::check()` accepts a path inside a package only when it is:

- 1 to 1 024 bytes of UTF-8;
- relative and separated by `/`: no leading `/`, no backslash, no colon — so no `C:` —, no control character;
- made of at most 32 segments, none empty, `.` or `..`.

A path that would leave the package is refused, never cleaned up. In a refusal, the path is shown with its control
characters replaced and cut to 120 characters.

`PackagePath::resolve()` reads a manifest's reference as a relative URI against its `xml:base` values: its query and
fragment set apart, its escapes decoded — so that `%2e%2e/` is `../` —, its `.` segments dropped, then checked.

## The manifests

`imsmanifest.xml` and `cmi5.xml` are parsed by PHP's DOM extension:

- a document longer than `manifestBytes` is refused before it is parsed;
- a document that declares a document type or an entity is refused, so that no entity — internal, external, or nested
  to expand — is ever resolved: neither XXE nor an entity expansion bomb can reach the parser;
- the parser runs with `LIBXML_NONET`, never reaching the network, and its errors are collected and cleared, leaving
  the error state of libxml as it was;
- the depth of the items or blocks and their number are bounded by `depth` and `items`, counted as they are read;
- an identifier is bounded to 255 bytes, a cmi5 URL to 2 048, a text to 4 096 characters, and a sequencing element
  larger than 64 KiB is not kept.

## What the library never does

- It never writes a file of the package to the disk, nor names one after the package: the caller stores the strings
  `read()` returns where it chooses, with the paths the library checked.
- It never fetches a URL a manifest names, nor resolves an external entity.
- It never runs, evaluates or unserializes anything a package holds.
- It keeps no state between two readings: `PackageReader` and the readers are immutable, and a `ZipPackage` belongs to
  one reading.

## Time and memory

The time of a reading grows with the size of the package, never with its square: the archive is checked entry by
entry, and the manifest walked once. A whole manifest is held in memory while it is read, which `manifestBytes`
bounds; a file is held in memory once read, which `entryBytes` bounds. The benchmarks of [Testing](testing.md) measure
a manifest of a thousand items and an archive of a thousand entries.
