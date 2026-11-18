# SCORM import

The bundle imports SCORM 1.2 and SCORM 2004 packages, to run them or to migrate courses from another platform. It reads
the package and its manifest, creates the course, and stores the files. A SCO runs framed from the content origin,
under the run-time API of its version, as [SCORM run time](scorm-runtime.md) explains; with the run time disabled, the
learner marks it done.

```sh
php bin/console phil:courses:import var/import/safety-course.zip
php bin/console phil:courses:import var/import/safety-course.zip --title="Workshop safety"
```

```php
$report = $importer->import('/path/to/package.zip');
$report->course;     // the new course
$report->warnings;   // what was left out or kept for information
```

## Reading the manifest

`imsmanifest.xml`, at the root of the archive, is read by phil/elearning-packages within the limits of [Security of
imported packages](security.md): no document type, no entity, a bounded size, depth and number of items. Elements are
matched by their local names, so that a manifest with or without the usual namespaces reads the same.

The version comes from `<schemaversion>`: `1.2` is SCORM 1.2; `CAM 1.3`, `1.3…` and any edition of `2004` are SCORM
2004. Without it, the namespaces of ADL's content packaging extensions decide. A manifest of another version is refused.

## What the package becomes

| In the manifest | In the course |
|---|---|
| The default organization, else the first | The course, its title the organization's |
| An item of the first level with children | A module; its leaves become its activities, the titles of the items between them prefixed, as in "Chisels / Care" |
| An item of the first level without children | An activity of a module named after the organization |
| A leaf whose resource has a file in the package | A `scorm` activity, its launch file resolved against every `xml:base`, and the item's `parameters` appended |
| A leaf whose resource is an absolute URL | A `link` activity |
| The manifest's identifier, and each item's | The source of the course, and of each activity |
| Every file of the archive | Stored under the course's directory |

Each activity is required. It is satisfied once passed when its item has a mastery score (SCORM 1.2) or a scaled passing
score (SCORM 2004: the `minNormalizedMeasure` of a primary objective satisfied by measure), once completed or passed
otherwise. The settings of a `scorm` activity keep the version, and what the run time hands the SCO: the mastery score
of SCORM 1.2 as a scaled score, the scaled passing score and the completion threshold of SCORM 2004, the launch data
(`adlcp:datafromlms`), the time allowed (`adlcp:maxtimeallowed`, or the `attemptAbsoluteDurationLimit` of SCORM 2004)
and its action (`adlcp:timelimitaction`). They keep for information the SCORM type of the resource, the prerequisites
of SCORM 1.2 and the sequencing of SCORM 2004.

## What is not imported

| Left out, or kept for information | Why |
|---|---|
| The other organizations | A course has one outline |
| Hidden items, and items that launch nothing | Nothing to show; the report names each |
| Launch files and declared files missing from the package | The activity is created; the report names each file, before the other notes |
| Sequencing and navigation rules of SCORM 2004, the sequencing collection | Kept with the activities, not enforced: impose the order of the course instead; the navigation requests a SCO makes are followed, as [SCORM run time](scorm-runtime.md#packages-of-several-scos) explains |
| Prerequisites of SCORM 1.2 | Kept, not enforced: impose the order of the course instead |
| Objectives of the sequencing other than the primary one | Left out: each SCO keeps its own `cmi.objectives` in its attempt |
| Learner results | A package carries none; see below |

## Importing results

A SCORM package carries no learner results: they live in the previous platform. Its export, as CSV, is imported for an
imported course:

```sh
php bin/console phil:courses:import-results 0192f0c1-5c5e-7a3b-9e1d-0123456789ab var/import/results.csv
```

The first line names the columns, in any order:

| Column | Holds |
|---|---|
| `learner` | The learner's stable identifier on this site, never an e-mail address |
| `item` | The identifier of the item in the manifest |
| `status` | The `lesson_status` of SCORM 1.2 — `passed`, `completed`, `failed`, `incomplete`, `browsed`, `not attempted` —, or the `completion_status` and `success_status` of SCORM 2004 joined by `+`, such as `completed+passed` |
| `score` | Optional: a score out of a hundred |
| `date` | Optional: the moment, in ISO 8601, or a date taken in UTC; now when empty |

The learner is enrolled when they are not. A line that cannot be read is reported by its number and left out, the
others imported; at most 100 000 lines are read. `ResultImporter::import()` does the same from rows the application
reads itself.
