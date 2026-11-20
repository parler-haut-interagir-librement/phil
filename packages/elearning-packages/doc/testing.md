# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` covers the archive, the paths, the safe XML reading, the limits, the readers of SCORM, cmi5 and xAPI,
`PackageReader`, the values, the run-time data model of both versions — every read and write rule, the error codes, the
API and its states, commits, statuses, scores and times —, and the sequencing: the reading of every element, each
navigation request, each rule family and condition, the rollup and its considerations, global objectives, selection and
reordering, the state and its bounds, and the prerequisites of SCORM 1.2. The sequencing tests walk hand-written
manifests built by `tests/Fixture/SequencingManifests.php`; a property test rolls up sixty random trees of random
results and checks them against the defaults computed apart, and against the same trees with their children reversed.
The packages the tests read — a SCORM 1.2 manifest, a SCORM 2004 manifest with sequencing, a cmi5 course structure — are
written for the tests in `tests/Fixture/Packages.php`, and zipped in the temporary directory;
`tests/Fixture/truncated.zip` is an archive whose data ends before its entry says, which the zip extension cannot write.
In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

Seven fuzz targets feed inputs nobody wrote to the readers, the data model and the sequencer, from the seeds of
`tests/Fuzz/corpus/<name>`:

| Target | Reads |
|---|---|
| `scorm-manifest` | `imsmanifest.xml`: read into a manifest whose default organization has items and whose launch files stay inside the package, or refused |
| `cmi5-structure` | `cmi5.xml`: read into a course of units with unique identifiers and mastery scores from 0 to 1, or refused |
| `zip-package` | A ZIP archive: refused, or opened with paths inside it and files read within the limits |
| `cmi-commit` | A commit as JSON, applied to the data of an attempt of each version: kept within its bound, loaded back, ended |
| `xapi-package` | `tincan.xml`: read into a course whose activities have unique IRIs and whose launch files stay inside the package, or refused |
| `prerequisites` | A prerequisites expression of SCORM 1.2: parsed into prerequisites that answer for every status and name only identifiers of the expression, or refused |
| `sequencing` | `imsmanifest.xml`: read into an activity tree, or refused; then walked by every navigation request, each state reading back from its plain values |

Each target fails on anything but a reading or a `PackageRefused`, warnings included; `cmi-commit` on any exception.

```sh
composer fuzz -- scorm-manifest cmi5-structure zip-package cmi-commit xapi-package prerequisites sequencing
```

## Benchmarks

`tests/Benchmark/ElearningPackagesBench.php` measures reading a SCORM manifest and a cmi5 course structure, a manifest
of a thousand items, checking an archive of a thousand entries, reading a whole package with its launch files checked,
applying a commit of SCORM 2004, loading, extending and ending the data of an attempt of 250 interactions, reading an
xAPI package of a thousand activities and the sequencing of a thousand activities, and, on a tree of a thousand
activities whose state is read back from its plain values, a report rolled up and a continue.

## Testing code that uses the library

Every value has a public constructor, and `PackageReader::read()` takes any `PackageArchive`: a test gives it an archive
in memory, its files by name, without writing a ZIP file.
