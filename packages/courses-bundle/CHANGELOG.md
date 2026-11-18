# Changelog

All notable changes to `phil/courses-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-18

### Added

- Courses, modules and activities, kept through DBAL, with the activity types `page`, `link`, `scorm` and `cmi5` as
  adapters of the `ActivityType` port, collected by the tag `phil_courses.activity_type`.
- Enrollments, progress by activity, the requirement of each activity in the values of cmi5's `moveOn`, completion by
  course, and the events `LearnerEnrolled`, `ActivityProgressed`, `ActivitySatisfied`, `CourseCompleted`,
  `CourseImported` and `StatementRecorded`.
- `Completions`, which answers whether a learner completed a course, with the enrollment and the moment, by the rule
  that raises `CourseCompleted`; `CertificatesCourseCompletions`, the adapter of the `CourseCompletions` port of
  phil/certificates-bundle 0.2, registered when that port is installed.
- Releases on a date or a number of days after the enrollment, from midnight in the site's time zone, and courses of
  imposed order.
- Second chances asked for, granted, refused or answered by a waiver of the activity, each decision with the
  teacher's note; `SecondChances::review()` and `RetryReview`; the events `RetryRequested`, `RetryGranted`,
  `RetryRefused` and `RetryWaived`, the trail of phil/audit-bundle, and `phil:courses:retries` with `--grant`,
  `--refuse`, `--waive` and `--note`.
- The administration page of the second chances, at the routes of `config/admin_routes.php`: the requests waiting for
  a decision and the form that grants, refuses or waives with a note, for the role of `phil_courses.admin.role`, with
  a CSRF token, drawn with the recipes of the kit in English and French.
- The import of SCORM 1.2 and SCORM 2004 packages, sequencing, prerequisites and mastery scores kept for information,
  and the import of results from a CSV export with `phil:courses:import-results`.
- The import of cmi5 packages and course structures, with `phil:courses:import`: the tree of the blocks kept as
  `Block` — nested blocks, their order among the children of their parent and their identifiers —, each unit naming
  its innermost block, a unit whose `moveOn` is `NotApplicable` not required.
- The packages read by phil/elearning-packages within the limits of `phil_courses.import.limits`, its refusals given
  as `PackageRefused`, and its `PackageReader` registered as a service with those limits.
- cmi5 launches with their parameters, the fetch URL, the xAPI endpoint that checks statements against the rules of
  cmi5, the `LMS.LaunchData` and state documents, the `launched`, `abandoned` and `waived` statements of the
  platform, its `satisfied` statements of each block, innermost first, then of the course, once for each enrollment,
  named by the identifiers of the course structure and issued at the next outcome to an enrollment that lacks them,
  with the `BlockSatisfied` event, and the `LearningRecordStore` port with a store on DBAL.
- `HttpLearningRecordStore`, which sends the statements to a remote xAPI learning record store through the guarded
  client of phil/safe-http, configured under `phil_courses.cmi5.remote_lrs`, its failures given as
  `RecordStoreUnavailable` and answered `503` by the xAPI endpoint, the state documents kept local in
  `DbalLearningRecordStore`.
- `CourseTables::upgrade()`, the statements that bring the tables of an earlier schema up to date.
- The content of the packages, stored apart and served from the content origin with its content security policy.
- The pages — outline, activity, enrollment, second chance — drawn with the recipes `Button`, `Badge`, `Progress` and
  `Textarea` of the kit, in English and French.
- The configuration `phil_courses`, the Flex recipe, the fuzz target of the statements, and the benchmarks of the hot
  paths.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
