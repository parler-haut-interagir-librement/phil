# Changelog

All notable changes to `phil/elearning-packages` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-20

XAPI packages read from `tincan.xml`, the SCORM 2004 sequencing and the SCORM 1.2
prerequisites run by a sequencer, and a manifest holding more than 500,000 XML nodes, or nesting its elements deeper
than 64 levels, refused before it is loaded. A minor version: `PackageFormat` gains the case `Xapi`, which a `match`
over its cases must now handle, `Package::$manifest` may be an `XapiCourse`, and the package requires `phil/url`.

### Added

- xAPI packages: `Xapi\XapiCourseReader` reads `tincan.xml` into an `Xapi\XapiCourse` — the course activity, its title
  in the language asked for, every activity in order — of `Xapi\XapiActivity` values: IRI, type, names and
  descriptions by language tag with `name()` and `description()`, launch, `Xapi\InteractionType`. What can be set
  aside is an `Xapi\XapiWarning` of an `Xapi\XapiWarningKind`; an activity without an IRI, no launchable activity, or
  a launch that leaves the package throws `PackageRefused`. A launch file is resolved by `PackagePath`; an absolute
  launch address is read by `phil/url` and kept in its canonical form.
- `PackageFormat::Xapi`, whose `manifest()` is `tincan.xml`. `PackageReader` reads a package as xAPI when `tincan.xml`
  is at its root and neither `cmi5.xml` nor `imsmanifest.xml` is, notes the issues set aside and the launch files the
  package does not hold, and `Package::identifier()` and `title()` give the IRI and the title of the course activity.
- The SCORM sequencing model in `Sequencing\`: `SequencingReader` reads an organization of `imsmanifest.xml` into an
  `ActivityTree` of `Activity` values, each with its `Sequencing` — `ControlMode`, `SequencingRule` lists by
  `RuleTiming` with their `RuleCondition`, `ConditionType` and `RuleAction`, `LimitConditions`, `RollupRule` lists with
  `RollupAction` and `ChildActivitySet`, `RollupControls` with the `Requirement` of each rollup consideration,
  `Objective` and `ObjectiveMap`, `RandomizationControls` and `RandomizationTiming`, `DeliveryControls` —, an entry of
  the sequencing collection filling what an item does not declare. A value it cannot use is noted and its default
  kept; the rules, conditions, objectives and maps of an activity are bounded, past which the manifest is refused.
- `Sequencing\Prerequisite\Prerequisites`, the prerequisites of SCORM 1.2 — `&`, `|`, `~`, parentheses, statuses
  compared by `=` and `<>`, sets `n*{…}` —, parsed within 4,096 bytes and 32 levels of nesting.
- `Sequencing\Sequencer`, a deterministic state machine over the tree: `navigate()` takes a `NavigationRequest` —
  `Start`, `ResumeAll`, `Continue`, `Previous`, `Choice`, `Exit`, `ExitAll`, `SuspendAll` — and returns a
  `SequencingResult` of an `Outcome`, the activity to launch and the new state; it applies the termination with the
  exit and post-condition rules, the pre-condition rules, the limits, the delivery of new and resumed attempts, the
  rollup of measure, satisfaction and completion with the default rules and the rollup considerations, the shared
  global objectives, the selection and reordering of children from the seed of the state, and the prerequisites of
  SCORM 1.2. `report()` records an `AttemptResult` — built from a `Runtime\RuntimeStatus` by `fromRuntime()` or from a
  `lesson_status` by `fromLessonStatus()` — and rolls it up; `lessonStatus()` gives the status of an item of SCORM 1.2.
- `Sequencing\SequencingState`, the state a platform keeps, with `begin()`, `toArray()` and `fromArray()`, checked
  against the tree; `ActivityStatus` and `ObjectiveStatus` read it.
- `Exception\SequencingRefused`, for a stored state that does not match its tree and for results reported without an
  attempt under way.
- The fuzz targets `xapi-package`, `prerequisites` and `sequencing`, and the benchmarks of an xAPI package and of the
  sequencing of a thousand activities, and of a report and a continue on a tree of a thousand activities.
- The pages `doc/reading-xapi.md` and `doc/scorm-sequencing.md`.

- `manifestNodes` and `manifestDepth` on `PackageLimits`, the XML nodes of a manifest — its elements, attributes, text
  and comments — and the depth of its elements; `PackageLimits::MANIFEST_NODES`, 500,000, and
  `PackageLimits::MANIFEST_DEPTH`, 64. `Scorm\ScormManifestReader` and `Cmi5\Cmi5CourseReader` throw
  `PackageRefused` at the first node past a budget.
- The seeds `many-nodes.xml` and `deep-elements.xml` of the fuzz target `scorm-manifest`, which reads under tight
  budgets of nodes and depth.

### Changed

- A manifest is counted by a streaming pass of `XMLReader`, one node at a time and holding no tree, before the DOM
  loads it: a manifest past a budget is refused before anything of it is built. The package requires the `xmlreader`
  extension, bundled with PHP.
- `PackageReader::read()` refuses a package holding none of `cmi5.xml`, `imsmanifest.xml` and `tincan.xml` at its
  root, and says so. `Runtime\ScormVersion::of()` throws `RuntimeRefused` for `PackageFormat::Xapi` as for cmi5.
- The package requires `phil/url`, and through it the `intl` and `uri` extensions.

## 0.1.1-alpha - 2026-11-18

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
