# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

`tests/Unit` covers the archive, the paths, the safe XML reading, the limits, both readers, `PackageReader` and the
values. The packages the tests read — a SCORM 1.2 manifest, a SCORM 2004 manifest with sequencing, a cmi5 course
structure — are written for the tests in `tests/Fixture/Packages.php`, and zipped in the temporary directory;
`tests/Fixture/truncated.zip` is an archive whose data ends before its entry says, which the zip extension cannot
write. In the monorepo, Infection holds the package at 100 % of covered mutants.

## Fuzzing

Three fuzz targets feed inputs nobody wrote to the readers, from the seeds of `tests/Fuzz/corpus/<name>`:

| Target | Reads |
|---|---|
| `scorm-manifest` | `imsmanifest.xml`: read into a manifest whose default organization has items and whose launch files stay inside the package, or refused |
| `cmi5-structure` | `cmi5.xml`: read into a course of units with unique identifiers and mastery scores from 0 to 1, or refused |
| `zip-package` | A ZIP archive: refused, or opened with paths inside it and files read within the limits |

Each target fails on anything but a reading or a `PackageRefused`, warnings included.

```sh
composer fuzz -- scorm-manifest cmi5-structure zip-package
```

## Benchmarks

`tests/Benchmark/ElearningPackagesBench.php` measures reading a SCORM manifest and a cmi5 course structure, a manifest
of a thousand items, checking an archive of a thousand entries, and reading a whole package with its launch files
checked.

## Testing code that uses the library

Every value has a public constructor, and `PackageReader::read()` takes any `PackageArchive`: a test gives it an archive
in memory, its files by name, without writing a ZIP file.
