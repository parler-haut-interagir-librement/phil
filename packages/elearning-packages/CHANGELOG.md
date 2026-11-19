# Changelog

All notable changes to `phil/elearning-packages` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-18

The next version is 0.1.1-alpha.

### Added

- The SCORM run-time data model in `Runtime\`: `DataModel`, the elements of SCORM 1.2 and SCORM 2004 4th edition with
  their types, vocabularies, ranges, read and write rules and the bounds of their collections, and `describe()`, the
  data model as a browser reads it; `CmiData`, which reads and writes the tracking data of an attempt as `GetValue()`
  and `SetValue()` answer, with the dependencies and unique identifiers of SCORM 2004 (`CmiRules`) and its evaluated
  statuses; `CmiCommit`, which applies a commit; `ScormSession`, the run-time API with its states; `CmiAttempt`,
  `LaunchSettings`, `Entry` and `SessionEnd`, a session's beginning and end; `RuntimeStatus`, `Score` and `Duration`;
  `ScormError` and `Mistake`, the error codes of each version and their messages; `RuntimeRefused`.
- `ScormItem::$dataFromLms`, `$maxTimeAllowed`, `$timeLimitAction` and `$scaledPassingScore`, read from the manifest.
- The fuzz target `cmi-commit`, and the benchmarks of a commit and of the end of a session.

### Fixed

- The package ships `tests/Fixture/truncated.zip`, the truncated archive a test of `ZipPackage` reads: it was left out
  of 0.1.0-alpha, and that test failed in a copy of the package.

## 0.1.0-alpha - 2026-11-17

### Added

- `PackageReader`, which reads a package whatever its format — cmi5 when `cmi5.xml` is at its root, else SCORM when
  `imsmanifest.xml` is —, from a file (`readFile()`), from an archive already opened (`read()`), or a cmi5 course
  structure alone (`readStructure()`), and notes the launch files and declared files the package does not hold and the
  resources an item names that the manifest does not describe.
- `Package`: the format, the SCORM manifest or the cmi5 course structure, the files of the package and the notes of the
  reading; `identifier()` and `title()`.
- `PackageFormat`: `scorm12`, `scorm2004` and `cmi5`, each with the file that describes it.
- `PackageLimits`: the size of the archive, its number of entries, the size of one file and of all of them once
  uncompressed, the compression ratio of a file, the size of a manifest, and the depth and number of its items.
- `ZipPackage` and `ZipPackageOpener`, which check a ZIP archive as a whole before any file is read — its size and
  entries, paths leaving it, names differing only by case, symbolic links, encrypted entries, announced sizes and
  compression bombs — and read each file through a stream bounded by what its entry announced, checked against its
  CRC-32 and counted against the total; the `PackageArchive` and `PackageOpener` contracts.
- `PackagePath`, which checks a path inside a package and resolves a manifest's reference against its `xml:base`
  values.
- `ScormManifestReader`, which reads `imsmanifest.xml` of SCORM 1.2 and SCORM 2004 into `ScormManifest`,
  `ScormOrganization`, `ScormItem` and `ScormResource`: the version from the schema version, else the namespaces; the
  launch files resolved and checked; mastery scores, prerequisites, completion thresholds and sequencing kept.
- `Cmi5CourseReader`, which reads `cmi5.xml` into `Cmi5Course`, `Cmi5Block` and `Cmi5Au` in the language asked for,
  with `MoveOn` and `LaunchMethod`; identifiers unique, values those cmi5 allows.
- The manifests read without document type or entity and without network access.
- `Warnings`, the notes of a reading, each said once and bounded.
- `ElearningPackagesException`, `PackageRefused` and `InvalidLimits`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The fuzz targets of the SCORM manifest, of the cmi5 course structure and of the ZIP archive, and the benchmarks of
  the readers.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
