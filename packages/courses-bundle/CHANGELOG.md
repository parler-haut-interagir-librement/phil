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
- The SCORM run time: the Stimulus controller `scorm-runtime`, served by AssetMapper, frames the frame of the run time
  on the content origin, which holds the run-time API of SCORM 1.2 (`API`) or SCORM 2004 (`API_1484_11`), checks each
  call by the data model of phil/elearning-packages and frames the SCO; the commits go to `POST /courses/scorm/commit`,
  with `navigator.sendBeacon()` when the learner leaves, authenticated by a signed, short-lived attempt token and the
  stateless CSRF token of the courses, and checked again on the server.
- `ScormRuntime`, `ScormTokens`, `ScormLaunch` and `CommitAnswer`: SCORM attempts resumed after a suspension, attempted
  again after an ended one, each launch opening the next session; progress recorded once the content reports a
  completion or a pass, the whole outcome at the end of a session, a failure counted once the attempt ends; the mastery
  score, scaled passing score, completion threshold, launch data and time allowed of the manifest handed to the
  content; the navigation requests of SCORM 2004 followed to the next, previous or chosen activity, or the outline.
- `ScormAttempt`, `AttemptState`, `ScormAttemptId`, the `ScormAttemptRepository` port and `DbalScormAttemptRepository`,
  with the tables `phil_course_scorm_attempt` and `phil_course_scorm_data`, their keys in `utf8mb4_bin` on MySQL and
  MariaDB.
- `ScormController`, `ScormCommitPayload` and `ScormFrameController`, the routes of the commit endpoint and of the frame,
  the `ScormSessionEnded` event and the `AttemptRefused` exception.
- `ScormPrivacy`: the SCORM attempts and their tracking data in the access and erasure requests of
  phil/privacy-bundle, when it is installed.
- The configuration `phil_courses.scorm` — `enabled`, `token_ttl`, `secret` — and `phil_courses.content.application_origin`.
- `ContentUrls::runtime()` and `CourseRequest::isTrustedToken()`.
- The activity page of a SCORM session with the recipes `Badge` and `Button` of the kit, in English and French.
- The fuzz target `scorm-commit`, the benchmark of a commit, the tests of the scripts, and the documentation
  [SCORM run time](doc/scorm-runtime.md).
- A SCORM activity reports its own progress through the run time; the learner marks it done only when
  `phil_courses.scorm.enabled` is false.
- An imported SCORM activity is satisfied once passed when its item has a mastery score or a scaled passing score, once
  completed or passed otherwise.
- `CourseTables::configure()` takes the platform, which the schema listener gives.
- The content is framed by the application's origin and by the content origin itself, which frames the SCOs under the
  run-time API; `phil_courses.content.application_origin`, else the origin of `cmi5.actor_home_page`, names the
  application's origin.
