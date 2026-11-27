# Changelog

All notable changes to `phil/courses-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.4.0-alpha - 2026-11-26

### Added

- The SCORM run time answers whether a choice or a jump to a target is valid: `adl.nav.request_valid.choice.{target=…}`
  and `adl.nav.request_valid.jump.{target=…}` read `true` for an item an activity of the course was imported from, and
  `false` for any other target. The page hands the content the targets of the first 500 such activities,
  `ScormRuntime::MAX_TARGETS`, with the session; they are never kept with the attempt.
- The responses of `fill-in`, `long-fill-in` and `performance` interactions are checked by their format, in the browser
  and on the server, by the data model of phil/elearning-packages.
- A statement of another verb than `completed`, `passed` and `failed` about the xAPI activity launched — `mastered`,
  `experienced`, a verb of the authoring tool — moves the learner's progress through its result: `result.completion`
  true completes the activity, a boolean `result.success` passes or fails it, and a `mastery_score` decides success
  from its scaled score; without either, it moves nothing.
- A state document posted to the xAPI endpoint is merged as xAPI asks of JSON documents: the posted object is kept as
  written when there is none yet, else its properties are added to the JSON object kept, or replace those of the same
  name, at the top level; a `POST` of anything but a JSON object, or over a document that is not one, answers `400`, and
  the merged document is bounded to 64 KiB. A `PUT` still replaces the document whole.
- The import of an xAPI package notes the correct responses of its questions that name a component the question does
  not declare, from the reader of phil/elearning-packages.
- Catalogues of courses: `Catalogues`, a public catalogue shown to everyone, visitors signed out included, and the
  catalogues of the units of an organization, each shown to the learners the `LearnerUnits` port names for that unit;
  courses offered at the end or before another — 1 000 a catalogue at most —, moved and withdrawn, each open to
  self-enrollment or not, free or at a `CoursePrice`, with 10 tags at most; `browse()`, a page of 20 courses filtered
  by words, tag and price, sorted as placed, by title, the newest first or by price, a group of equivalent courses
  listed once in the learner's language, with where the learner stands in each — enrolled, completed or an equivalent
  course completed, mandatory for them —; `enroll()`, self-enrollment in a free course, `EnrollmentRefused` otherwise;
  `grantPurchase()`, the enrollment in a course paid for. `Catalogue`, `CatalogueEntry`, `CatalogueId`,
  `CatalogueAudience`, `CatalogueSort`, `PriceFilter`, `OfferedCourse`, `CatalogueQuery`, `CataloguePage`,
  `CatalogueItem`, the port `CatalogueRepository` and `DbalCatalogueRepository`, with the tables `phil_course_catalogue`
  and `phil_course_catalogue_entry`; removing a course removes its entries in the catalogues.
- `OrganizationsLearnerUnits`: with phil/organizations-bundle registered, the units of a learner and the units above
  them from its tree of units, in each organization the learner is a member of; the option
  `phil_courses.catalogues.units_below`, true by default, false to keep the catalogue of a unit to its own members; the
  option `phil_courses.catalogues.learner_units` names another `LearnerUnits`.
- The courses sold through phil/commerce-bundle, when it is registered: `CourseSellables`, the `SellableResolver` of the
  references of type `course`, `<catalogue>/<course>`, at the price of the course in that catalogue under the tax
  category `phil_courses.catalogues.tax_category`; `CoursePurchases`, which enrolls the customer of an order in its
  courses at each `OrderPurchased`; the port `CourseCheckout`, named in `phil_courses.catalogues.checkout`, which sends
  the learner to the application's cart, at an address of the site itself.
- The pages of the catalogues, `phil_courses_catalogues`, `phil_courses_catalogue` and `phil_courses_catalogue_enroll`,
  with the recipes `Button`, `Badge`, `Input` and `NativeSelect` of the kit, their texts `catalogue.*` in English and
  French, and the Twig function `phil_courses_price()`.
- The fuzz target `catalogue-query`, on the query string of a catalogue page, and the benchmark of a page of a catalogue
  of 500 courses.
- The documentation page catalogues.
- The completions of the transcripts as the training source of phil/reporting-bundle, when it is registered and the
  mandatory training enabled: `CoursesTrainingSource`, an activity for each completion of the period, its hours from
  the credits it granted — continuing education units at `phil_courses.reporting.ceu_hours` hours each, 10 by
  default —, its cost from the port `TrainingCosts` and `TrainingCost`, its unit from the port `TrainingUnits`; the
  options `phil_courses.reporting.enabled`, `currency`, `ceu_hours`, `costs` and `units`; `OrganizationsTrainingUnits`,
  the learner's unit from the tree of units of phil/organizations-bundle when it is registered;
  `TranscriptRepository::completedBetween()`, the completions of a span of time read row by row, and the index
  `idx_phil_course_transcript_completed` that `CourseTables::upgrade()` adds; the constant `Credits::CEU_HOURS`; the
  documentation page training reports.

## 0.3.1-alpha - 2026-11-25

### Changed

- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.
- The development requirements accept `phil/certificates-bundle` 0.3, `phil/notifications-bundle` 0.4 and `phil/ux-kit`
  0.6, the minor versions of their main branches and no earlier one, so that the package installs on its own beside
  those packages.

### Fixed

- The transcript page: the header cell of each row of totals, "Total", covers the first column only, the second
  cell is empty, so that no header cell of the table spans two columns.

## 0.3.0-alpha - 2026-11-24

XAPI packages are imported through the reader of phil/elearning-packages, courses
follow each other in learning paths, and one training given in several languages forms a group of equivalent courses. A
minor version and not a patch: five tables are added, and `Completions` answers the completion of an equivalent course
and takes `Equivalences` as its second argument.

### Added

- `ElearningXapiPackageReader`, the reader of xAPI packages on the `XapiCourseReader` of phil/elearning-packages: the
  `tincan.xml` of the archive read within the limits of the import, the IRI, title and description of the course
  activity in the language of the import, the launchable activities in the order of the package, the notes of the
  reading as the warnings of the import, its refusals as `PackageRefused`.
- Learning paths: `LearningPaths`, an ordered sequence of courses, each step required or not and waiting for courses
  placed before it — 100 steps and 50 prerequisites a step at most —, added at the end or before another step, removed
  from the path and from the prerequisites of the others; `enroll()`, which enrolls the learner in the path once, then
  in each course whose prerequisites they completed; `progress()`, with `PathProgress`, `StepStatus` and `StepState`;
  `advance()` and `completion()`. `PathCompletions` advances the learner's open enrollments in the paths that hold a
  course at each `CourseCompleted`, and completes a path once its required steps are.
- `LearningPath`, `PathStep`, `PathEnrollment`, `LearningPathId`, `PathEnrollmentId`, the events
  `LearnerEnrolledInPath` and `PathCompleted`, the port `LearningPathRepository` and `DbalLearningPathRepository`, with
  the tables `phil_course_path`, `phil_course_path_step` and `phil_course_path_enrollment`.
- `CertificatesCourseCompletions` answers a completion source `path:<identifier>` with the learner's enrollment in the
  completed path.
- `PathsPrivacy`: a learner's enrollments in paths in the access and erasure requests of phil/privacy-bundle, under the
  module `courses_paths`.
- Groups of equivalent courses: `Equivalences`, one course per language in a group — 50 courses at most, a course in
  one group at most —, `equivalents()` and `courseFor()`, the course of the learner's language by its tag, then by its
  primary language; `EquivalenceGroup`, `EquivalentCourse`, `EquivalenceGroupId`, `LanguageTag`, the port
  `EquivalenceRepository` and `DbalEquivalenceRepository`, with the tables `phil_course_equivalence` and
  `phil_course_equivalent`.
- The outline lists the other courses of the course's group, and the route `phil_courses_language`,
  `GET /{course}/language`, redirects to the course of the request's locale; the translation `outline.equivalents` in
  English and French.
- The documentation pages learning paths and equivalent courses.

### Changed

- An xAPI package is imported through `ElearningXapiPackageReader` when `phil_courses.import.xapi_reader` is null; a
  service named there replaces it.
- `Completions::completion()` answers, for a learner who did not complete the course itself, the earliest completion of
  an equivalent course; `Completion::$course` names the course completed. `Completions` takes `Equivalences` as its
  second argument.
- The completion of a course completes the obligations under its equivalent courses too, and a member reached by an
  assignment who completed an equivalent course starts completed; `Assignments` takes `Equivalences` as its last
  argument.
- A course without credits of its own grants those of the first course of its group, and a completion that fulfils no
  obligation while the transcript holds an equivalent course enters it without credits; `Transcripts` takes
  `Equivalences` as its last argument.
- Removing a course removes its steps in the learning paths and its place in a group of equivalent courses.
- The bundle is tested with phil/notifications-bundle 0.2 and 0.3.
- `CertificatesCourseCompletions` and `CourseController` take `LearningPaths` and `Equivalences` as their last
  argument.
- Upgrade: `CourseTables::upgrade()` creates the five tables of the learning paths and the equivalent courses; with the
  ORM, `doctrine:migrations:diff` writes the same migration. An application that implements `CourseRepository` removes
  the steps and the group membership of a removed course.
- The development requirements accept `phil/notifications-bundle` 0.3, `phil/privacy-bundle` 0.3 and `phil/safe-http`
  0.2, the minor versions of their main branches and no earlier one, so that the package installs on its own beside those
  packages.
- Requires the latest minor of each dependency (`doctrine/dbal` 4.6, `phil/elearning-packages` 0.2), and of each
  development requirement (`doctrine/orm` 3.8, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31,
  `symfony/ux-twig-component` 3.8, …). The dates of a row are read through the type provider of the connection, as
  DBAL 4.6 asks, instead of the static `Type::getType()`.

### Fixed

- `phil/elearning-packages` 0.2 is accepted beside 0.1: the package did not install beside the version published on
  2026-11-20.

### Security

- The SCORM attempt tokens and the cmi5 session tokens were the HMAC under `scorm.secret` and `cmi5.secret`
  themselves, `%kernel.secret%` by default, which other bundles keyed their own hashes with. `ScormTokens` and
  `Cmi5Tokens` derive their keys by HKDF-SHA256 under `phil_courses.scorm_token` and `phil_courses.cmi5_token`
  (`ScormTokens::USAGE`, `Cmi5Tokens::USAGE`). A session open at the update loses its token: the learner launches the
  unit again.

## 0.2.0-alpha - 2026-11-20

An ended SCORM attempt opens for review, the time allowed is enforced on the server,
the cmi5 records of a learner join the access and erasure requests, xAPI packages run as activities, and courses can be
assigned by a due date, reminded, renewed for a recertification, and credited in a transcript. A minor version and not
a patch: a commit that reaches the deadline of an attempt whose time limit exits now ends it, and a later one is no
longer kept; `LaunchSessionRepository` has two methods more and `EnrollmentRepository` one; `phil_course_scorm_attempt`
has two columns more and four tables are added; the format of an import is a `CourseOrigin`; and the bundle requires
phil/recurrence. The two new budgets of a manifest are options of the bundle, with the defaults of
phil/elearning-packages 0.1.2.

### Added

- The Flex recipe in `recipe/0.2`, whose configuration names `training.manager_role`; `recipe/0.1` keeps serving the
  versions 0.1.
- `phil_courses.import.limits.manifest_nodes` and `phil_courses.import.limits.manifest_depth`, the XML nodes of a
  manifest and the depth of its elements, passed to `PackageLimits`.
- The review of an ended SCORM attempt: `POST /{course}/activities/{activity}/review`, the route
  `phil_courses_review`, the form **Review the last attempt** of the outline beside a reached SCORM activity with an
  ended attempt, `Learning::review()` and `reviewable()`, `ScormActivity::review()` and `reviewable()`,
  `ScormRuntime::review()` and `reviewable()`. The content reads the data of the learner's last ended attempt in the
  mode `review`, without credit; no session is opened, the page is given an empty token and sends nothing, the activity
  page says so with a badge and its live region, and nothing is recorded.
- `ScormLaunch::$review` and `$remaining`, and the keys `review` and `remaining` of the state handed to the frame.
- `AttemptRefused::ended()`: a commit to an ended attempt is refused with `409`, whatever its token.
- The time limits of SCORM attempts: `TimeLimit`, the time of the manifest with its action, the activity's own
  `time_limit` setting in seconds — from 1 to 35 999 999, checked by `ScormActivity::settings()` —, or the shorter, the
  content handed that time and its action. The platform counts the seconds the sessions of an attempt run by its own
  clock, and ends the attempt at the deadline with the exit `time-out`; a commit later than `ScormRuntime::GRACE`
  seconds past it is not kept; a session left running, or an attempt suspended, past its deadline ends at the next
  launch; an attempt ended by its time and neither completed nor passed counts as failed.
- `ScormAttempt::$elapsed`, `$sessionOpenedAt`, `spent()` and `expire()`, kept in the columns `elapsed` and
  `session_opened_at` of `phil_course_scorm_attempt`.
- `CommitAnswer::$remaining` and `$timeUp`, and the keys `remaining` and `time_up` of the answers of
  `POST /courses/scorm/commit`; the Stimulus controller `scorm-runtime` commits at the deadline and stops once the time
  is up.
- `Cmi5Privacy`: the cmi5 launch sessions, statements, state documents and notes of the `satisfied` statements of a
  learner's registrations in the access and erasure requests of phil/privacy-bundle, under the module `courses_cmi5`;
  with a remote learning record store or the application's own, the export names the store, the records it keeps and
  the registrations, and the erasure reports it as surviving.
- `LaunchSessionRepository::ofEnrollments()` and `removeOfEnrollments()`; `DbalLearningRecordStore::statementsOf()`,
  `statesOf()` and `erase()`; `HttpLearningRecordStore::$endpoint`, without the credentials.
- The vectors of `tests/js/fixture/review.json`, run by the server and the browser alike, and the translations of the
  review and of the time up in English and French.
- The activity type `xapi`, `XapiActivity`, for the activities of xAPI packages: its settings `activity_id`, `url` or
  `path` and `query`, `launch_method` and `mastery_score`. `XapiLauncher` launches it with the parameters `endpoint`,
  `auth`, `actor`, `registration` and `activity_id`, the token of a session of its own given at once; the session
  still open is abandoned.
- `XapiStatements`, which receives the statements of an xAPI activity at the xAPI endpoint of cmi5, `XapiRules`, which
  checks them — the actor the learner of the session, a verb named by an IRI and never `voided`, an object that is an
  activity or a statement reference, the registration of the launch, a UUID identifier, a scaled score from -1 to 1 —,
  `XapiOutcomes`, which reads the completion, the success and the score of `completed`, `passed` and `failed` about
  the activity launched, with its mastery score, `XapiContext` and `XapiVerb`. The state documents of an xAPI
  activity belong to its `activity_id`.
- `StatementBatch`, the bounds of the body of a request to the statements resource, shared by cmi5 and xAPI.
- `LaunchUrl`, the parameters of a launch added to the URL of its content, before its fragment.
- The import of xAPI packages: an archive holding `tincan.xml` and neither `cmi5.xml` nor `imsmanifest.xml` is read by
  the `XapiPackageReader` port, named in `phil_courses.import.xapi_reader`, into an `XapiPackage` of `XapiLaunchable`
  activities, which `XapiPlanner` turns into a course of one module; without a reader, it is refused.
  `CourseOrigin::Xapi`.
- The mandatory training: `Assignments` assigns a course to a learner, a group or a unit by a due moment, reaches the
  members an `AssigneeMembers` adapter names — at most `phil_courses.training.max_members` —, enrolls them, gives each
  an `Obligation` and tells them; a member who completed the course already starts completed. `Assignment`,
  `AssigneeKind`, `ObligationState`, `TrainingStatus`, the learner's courses with `of()` and those overdue or due soon
  with `attention()`.
- `TrainingRun`, with `TrainingReport` and `TrainingSettings`: the new members of groups and units, the reminders before,
  on and after the due moment once a cycle, the person who assigned the course told once it is overdue, and the
  renewal of the recertifications; the obligations read a batch at a time. `ReminderStage`, `TrainingNotice`.
- Recertification: an assignment renewed after 1 to 120 months, its renewal read by phil/recurrence through
  `Recertification`, the next cycle due `phil_courses.training.renewal_grace_days` after it, the learner's enrollment
  starting again with `Enrollments::renew()`, `Enrollment::reopen()` and `EnrollmentRepository::removeProgresses()`.
- `RunTraining`, its handler, and the schedule `phil_courses_training` of Symfony Scheduler, every day at
  `phil_courses.training.run_at` in the site's time zone; `phil:courses:training` runs it from the console.
- The `TrainingNotifier` port and `NotificationsTrainingNotifier`, which sends the types `courses.training.assigned`,
  `due_soon`, `due`, `overdue`, `overdue_manager` and `renewed` through phil/notifications-bundle, identifiers only; the
  bundle declares them, with their templates in English and French.
- Credits in continuing education units or hours, `Credits` and `CreditUnit`, set with `Transcripts::credit()` or
  `phil:courses:credits`; `TrainingCompletions`, which completes the obligations then enters each completion in the
  learner's transcript, `TranscriptEntry`; `Transcript`, its totals, its array and its CSV, whose cells read as formulas
  are quoted.
- The pages `phil_courses_training` and `phil_courses_transcript` — as a page, `.json` and `.csv` — for the learner, and
  `phil_courses_admin_training` and `phil_courses_admin_transcript` for the managers who hold
  `phil_courses.training.manager_role`; `TrainingController` and the templates `training`, `training_admin` and
  `transcript`.
- `phil:courses:assign`, which assigns a course by the last day to complete it in the site's time zone.
- `TrainingPrivacy`: the obligations and the transcript of a learner in the access and erasure requests of
  phil/privacy-bundle, under the module `courses_training`; the transcripts survive an erasure with
  `phil_courses.training.keep_transcripts`.
- The events `TrainingAssigned`, `ObligationOverdue`, `ObligationRenewed`, `EnrollmentRenewed` and `CreditsGranted`.
- The tables `phil_course_assignment`, `phil_course_obligation`, `phil_course_transcript` and `phil_course_credits`;
  `AssignmentRepository`, `TranscriptRepository`, `DbalAssignmentRepository` and `DbalTranscriptRepository`.
- The configuration `phil_courses.training`, and the documentation pages xAPI activities, mandatory training, and
  credits and transcripts.

### Changed

- A commit that reaches the deadline of an attempt — its activity's own `time_limit`, or a time of the manifest whose
  action exits — ends the session and the attempt, and a commit later than 30 seconds past it is not kept.
- The commit of an ended attempt is refused with the message of `AttemptRefused::ended()`, still `409`.
- The content is handed the time allowed as the platform reads it: the shorter of the manifest's and the activity's,
  written as its version writes a time.
- Upgrade: `CourseTables::upgrade()` adds the nullable columns `elapsed` and `session_opened_at` to
  `phil_course_scorm_attempt`; with the ORM, `doctrine:migrations:diff` writes the same migration. The attempts kept
  before count their time from their next session.
- Upgrade: an application that implements `LaunchSessionRepository` adds `ofEnrollments()` and `removeOfEnrollments()`.
- The xAPI endpoint receives the statements of the sessions of xAPI activities through `XapiStatements`, and those of
  cmi5 through the rules of cmi5; `Cmi5Controller` takes `XapiStatements` as its last argument.
- `Cmi5Privacy` gives and erases the launch sessions and statements of the xAPI activities with those of cmi5.
- `ImportPlan::$format` and `ImportReport::$format` are a `CourseOrigin`, of the same values as the format of the
  package; `CourseOrigin::of()` names the origin of a format by its value.
- The bundle requires phil/recurrence.
- Upgrade: an application that implements `EnrollmentRepository` adds `removeProgresses()`; `CourseTables::upgrade()`
  adds the four tables of the mandatory training, and with the ORM `doctrine:migrations:diff` writes the same
  migration.

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
