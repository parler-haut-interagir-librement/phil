# Reference

Every class of the library, by namespace under `Phil\ElearningPackages`.

## Reading a package

| Class | Role |
|---|---|
| `PackageReader` | Reads a package whatever its format: `readFile(string $path)`, `read(PackageArchive $archive)`, `readStructure(string $xml)`; returns a `Package` |
| `Package` | The format, the manifest (`ScormManifest`, `Cmi5Course` or `XapiCourse`), the files and the warnings of a reading; `identifier()`, `title()` |
| `PackageFormat` | `Scorm12`, `Scorm2004`, `Cmi5`, `Xapi`; `manifest()` names the file that describes the format |
| `PackageLimits` | The bounds of a reading; its constants hold the defaults |
| `PackagePath` | `check()` a path inside a package, `resolve()` a manifest's reference against its bases, `isWebAddress()` |
| `Warnings` | The notes of a reading, each said once, at most `Warnings::MAX` and one counting those left out |

## The archive

| Class | Role |
|---|---|
| `Contract\PackageOpener` | Opens a package for reading: `open(string $path, PackageLimits $limits): PackageArchive` |
| `Contract\PackageArchive` | A package opened: `files()`, `has()`, `read()`, `close()` |
| `Archive\ZipPackageOpener` | The opener of ZIP archives |
| `Archive\ZipPackage` | A ZIP archive checked before any file is read, its files read within the limits; `ZipPackage::open()` |

## SCORM

| Class | Role |
|---|---|
| `Scorm\ScormManifestReader` | Reads `imsmanifest.xml` of SCORM 1.2 or SCORM 2004; `FILE` names it |
| `Scorm\ScormManifest` | The identifier, format, schema version, organizations, resources by identifier, default organization and sequencing collection; `organization()` |
| `Scorm\ScormOrganization` | An organization: identifier, title, items, sequencing |
| `Scorm\ScormItem` | An item: identifier, title, resource, parameters, visibility, children, mastery score, prerequisites, completion threshold, sequencing, launch data, time allowed and its action, scaled passing score; `isLeaf()` |
| `Scorm\ScormResource` | A resource: identifier, SCORM type, launch file, files, dependencies; `isSco()` |

## The SCORM run time

| Class | Role |
|---|---|
| `Runtime\ScormVersion` | `Scorm12`, `Scorm2004`; `of()` a package format, `apiName()`, `dataModelVersion()`, `statusElement()`, `core()` |
| `Runtime\DataModel` | The elements of a version, its collections and their bounds, `children()`, `responsePattern()`, `describe()` for the browser |
| `Runtime\ElementDefinition`, `Access`, `ValueCheck` | An element: who reads and writes it, its pattern, length, range and default |
| `Runtime\ElementName` | A name read into its pattern, indexes and keyword |
| `Runtime\CmiData` | The tracking data of an attempt: `get()`, `set()`, `put()`, `forget()`, `values()`, `count()`, `status()`, `scaledScore()` |
| `Runtime\CmiRules` | The rules of SCORM 2004 that tie an element to others: dependencies, unique identifiers, evaluated statuses |
| `Runtime\CmiCommit` | Applies a commit, giving the elements left out with their error codes |
| `Runtime\ScormSession`, `SessionState` | The run-time API of one session and its states |
| `Runtime\CmiAttempt`, `LaunchSettings`, `Entry`, `SessionEnd` | What the platform sets when a session begins, and decides when it ends |
| `Runtime\RuntimeStatus`, `Score`, `Duration` | Completion, success and scaled score; times of each version |
| `Runtime\RuntimeResult`, `ScormError`, `Mistake` | What a call answers; the codes of each version and their messages |

## cmi5

| Class | Role |
|---|---|
| `Cmi5\Cmi5CourseReader` | Reads `cmi5.xml` in the language asked for; `FILE` names it |
| `Cmi5\Cmi5Course` | The course: IRI, title, blocks and units, description, whether it holds objectives |
| `Cmi5\Cmi5Block` | A block: IRI, title, blocks and units, description |
| `Cmi5\Cmi5Au` | An assignable unit: IRI, title, URL, `moveOn`, mastery score, launch method, description, launch parameters, entitlement key, activity type |
| `Cmi5\MoveOn` | `Completed`, `Passed`, `CompletedAndPassed`, `CompletedOrPassed`, `NotApplicable`; `isMetBy()` |
| `Cmi5\LaunchMethod` | `AnyWindow`, `OwnWindow` |

## xAPI

| Class | Role |
|---|---|
| `Xapi\XapiCourseReader` | Reads `tincan.xml` in the language asked for; `FILE` names it, `MAX_LANGUAGES` bounds the texts of an activity, `MAX_RESPONSES` the correct responses of a question and `MAX_COMPONENTS` each of its lists |
| `Xapi\XapiCourse` | The course activity, its title, every activity and the warnings; `id()`, `activity()`, `launchable()`, `interactions()` |
| `Xapi\XapiActivity` | An activity: IRI, type, names and descriptions by language, launch, interaction type, correct responses, lists of components; `name()`, `description()`, `isLaunchable()`, `components()`, `definition()`; `COURSE` and `INTERACTION` name the two types it knows |
| `Xapi\InteractionType` | The interaction types of xAPI |
| `Xapi\ComponentList` | The lists of components of a question — `Choices`, `Scale`, `Source`, `Target`, `Steps` —; `of()` gives those an interaction type uses |
| `Xapi\InteractionComponent` | A component of a question: its identifier and its descriptions by language; `description()` |
| `Xapi\XapiWarning`, `XapiWarningKind` | An issue set aside: its kind, its activity and its sentence |

## Sequencing

| Class | Role |
|---|---|
| `Sequencing\SequencingReader` | Reads the activity tree of an organization of `imsmanifest.xml`; its constants bound the rules, conditions, objectives and maps of an activity |
| `Sequencing\ActivityTree` | The root, the format and the warnings; `get()`, `has()`, `parent()`, `path()`, `activities()`, `isScorm12()` |
| `Sequencing\Activity` | An activity: identifier, title, sequencing, children, visibility, resource, prerequisites; `isLeaf()` |
| `Sequencing\Sequencing` | Its control modes, rules by timing, limits, rollup rules and controls, objectives, randomization and delivery controls; `primaryObjective()`, `objective()`, `rules()` |
| `Sequencing\ControlMode`, `LimitConditions`, `DeliveryControls`, `RandomizationControls`, `RandomizationTiming` | The elements of `<imsss:sequencing>` of the same names |
| `Sequencing\SequencingRule`, `RuleCondition`, `ConditionType`, `RuleAction`, `RuleTiming` | A sequencing rule, its conditions and its action |
| `Sequencing\RollupRule`, `RollupAction`, `ChildActivitySet`, `RollupControls`, `Requirement` | A rollup rule, and how a child counts in its parent's rollup |
| `Sequencing\Objective`, `ObjectiveMap` | An objective and its maps to global objectives; `Objective::PRIMARY` keys a primary objective without identifier |
| `Sequencing\Prerequisite\Prerequisites` | The prerequisites of an item of SCORM 1.2: `parse()`, `isMet()`, `identifiers()`; `STATUSES` lists the statuses of SCORM 1.2 |
| `Sequencing\Sequencer` | `navigate()`, `report()`, `lessonStatus()` |
| `Sequencing\NavigationRequest` | `Start`, `ResumeAll`, `Continue`, `Previous`, `Choice`, `Exit`, `ExitAll`, `SuspendAll` |
| `Sequencing\SequencingResult`, `Outcome` | What a request led to: the outcome, the state, the activity delivered, the reason of a refusal |
| `Sequencing\SequencingState` | The state a platform keeps: `begin()`, `toArray()`, `fromArray()`, `activity()`, `global()`, `globals()` |
| `Sequencing\ActivityStatus`, `ObjectiveStatus` | What a state knows of an activity and of an objective |
| `Sequencing\AttemptResult` | What the content of the delivered activity reported: `fromRuntime()`, `fromLessonStatus()` |

## Exceptions

| Class | Role |
|---|---|
| `Exception\ElearningPackagesException` | The interface of every exception the library throws on purpose |
| `Exception\PackageRefused` | A package, a path or a manifest that cannot be read safely |
| `Exception\InvalidLimits` | A bound of `PackageLimits` that is not positive |
| `Exception\RuntimeRefused` | Tracking data or launch settings that cannot be used |
| `Exception\SequencingRefused` | A sequencing state that does not match its tree, or results reported without an attempt under way |

## Internal

| Class | Role |
|---|---|
| `Xml\XmlDocument` | The safe XML reading the readers share: not part of the public API |
| `Sequencing\SequencingBuilder` | Builds the activities of an organization for `SequencingReader` |
| `Sequencing\SequencingRun`, `SequencingData`, `SequencingRules`, `SequencingFlow`, `SequencingRollup`, `SequencingAttempts` | One request of the sequencer on a working copy of the state |
| `Sequencing\Prerequisite\Node`, `ItemStatus`, `Negation`, `AtLeast` | The parts of a prerequisites expression |

## Files

| File | Role |
|---|---|
| `phil-dev.php` | The paths, the fuzz targets and the words of the documentation, for the tooling of the monorepo |
| `composer-dependency-analyser.php` | The declared dependencies, checked against the package installed on its own |
| `tests/Fuzz/` | The fuzz targets and their corpus |
| `tests/Benchmark/` | The benchmarks of the readers, of the commits and of the sequencer |
