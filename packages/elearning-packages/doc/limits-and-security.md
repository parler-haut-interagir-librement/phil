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
| `manifestBytes` | 4 MiB | The size of `imsmanifest.xml`, `cmi5.xml` or `tincan.xml` |
| `depth` | 16 | How deep the items of a SCORM organization or the blocks of a cmi5 structure nest |
| `items` | 5 000 | The items, the resources, the blocks and units, or the xAPI activities a manifest describes |
| `manifestNodes` | 500 000 | The XML nodes of a manifest — its elements, attributes, text and comments —, counted before it is loaded |
| `manifestDepth` | 64 | How deep the XML elements of a manifest nest, its root at 1, checked before it is loaded |

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

`imsmanifest.xml`, `cmi5.xml` and `tincan.xml` are parsed by PHP's DOM extension:

- a document longer than `manifestBytes` is refused before it is parsed;
- a streaming pass of `XMLReader` counts its nodes and checks the depth of its elements one node at a time, holding no
  tree, and refuses a document past `manifestNodes` or `manifestDepth` at the node past the budget, before the DOM
  builds anything of it: a manifest that fits in its bytes cannot grow into more nodes than the budget;
- a document that declares a document type or an entity is refused, so that no entity — internal, external, or nested
  to expand — is ever resolved: neither XXE nor an entity expansion bomb can reach the parser;
- the parser runs with `LIBXML_NONET`, never reaching the network, and its errors are collected and cleared, leaving
  the error state of libxml as it was;
- the depth of the items or blocks and their number are bounded by `depth` and `items`, counted as they are read;
- an identifier is bounded to 255 bytes, a cmi5 URL and an xAPI IRI to 2 048, a text to 4 096 characters, the texts
  of an xAPI activity to 32 languages, and a sequencing element larger than 64 KiB is not kept as XML;
- an xAPI launch is a file of the package, checked as any path, or an absolute `http` or `https` address read by
  `phil/url` — never `javascript:`, `data:`, an address with a user name and a password, or an IP address;
- the sequencing an activity declares is bounded to 32 rules of one timing, 32 rollup rules, 16 conditions per rule,
  64 objectives and 16 maps per objective, and a SCORM 1.2 prerequisites expression to 4 096 bytes and 32 levels of
  nesting.

## The sequencing state

A `SequencingState` comes back from the platform's storage, which the learner's requests may have reached:
`SequencingState::fromArray()` checks its version, every type, each identifier against the tree, attempts up to a
billion, durations up to 9 999 hours, measures from -1 to 1, the order of children against the children of their
activity, at most 64 objectives per activity and 10 000 global objectives. A navigation request is bounded by the
tree: the flow takes at most twice as many steps as the tree has activities.

## The tracking data of the run time

The values a SCORM commit carries come from content the platform did not write, through the learner's browser:

- an element name is 255 bytes at most, of lowercase letters, digits, `_` and `.`, with indexes of four digits at most
  and no leading zero; a collection index past the next free one, or past the collection's bound, is refused;
- a value is checked against the pattern, the length in characters and the range of its element, and must be UTF-8;
- the data of an attempt is bounded at 1 MiB in all, a commit at 4 000 values;
- a time is bounded at 9 999 hours, however many digits it writes.

## What the library never does

- It never writes a file of the package to the disk, nor names one after the package: the caller stores the strings
  `read()` returns where it chooses, with the paths the library checked.
- It never fetches a URL a manifest names, nor resolves an external entity.
- It never runs, evaluates or unserializes anything a package holds.
- It keeps no state between two readings: `PackageReader` and the readers are immutable, and a `ZipPackage` belongs to
  one reading. The `Sequencer` keeps nothing between two requests: the state comes in and goes out with each. A
  `CmiData` belongs to one attempt; the definitions of the data model are built once per process, the same for every
  request.

## Time and memory

The time of a reading grows with the size of the package, never with its square: the archive is checked entry by
entry, and the manifest read twice, once by the streaming pass that counts it and once into a tree. A whole manifest
is held in memory while it is read, which `manifestBytes` and `manifestNodes` bound; a file is held in memory once read,
which `entryBytes` bounds. A request of the sequencer grows with the size of the tree: each rollup reads the children of
each activity on the path to the root. The benchmarks of [Testing](testing.md) measure a manifest of a thousand items,
an archive of a thousand entries, and requests of the sequencer on a tree of a thousand activities.
