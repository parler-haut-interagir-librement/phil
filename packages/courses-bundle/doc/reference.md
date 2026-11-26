# Reference

## Bundle

| Class or file | Role |
|---|---|
| `PhilCoursesBundle` | The bundle: its configuration `phil_courses` and the stateless CSRF token `phil_courses` |
| `config/routes.php` | The routes of the pages |
| `config/cmi5_routes.php` | The fetch URL and the xAPI endpoint |
| `config/content_routes.php` | The files of the imported packages and the frame of the SCORM run time, on the content origin |
| `config/admin_routes.php` | The administration pages: the second chances, the training overdue or due soon, the transcripts |
| `templates/` | `layout`, `outline`, `activity`, `second_chances`, `training`, `training_admin` and `transcript`, drawn with the recipes of the kit |
| `translations/` | The words of the pages, and the notifications of the mandatory training in the domain `phil_notifications`, in English and French |
| `assets/src/scorm_runtime_controller.js` | The Stimulus controller of the activity page of a SCORM session: frames the frame, sends the commits |
| `assets/src/scorm_frame.js`, `assets/src/lib/api.js`, `assets/src/lib/cmi.js` | The frame of the run time on the content origin, the run-time API, and the data model in the browser |
| `recipe/0.2` | The Flex recipe: the bundle, its configuration and its routes (`recipe/0.1` for the versions 0.1) |

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Course` | A course: title, description, origin, source, imposed order |
| `Domain\Model\Module` | A module: title, place, release |
| `Domain\Model\Activity` | An activity: type, settings, requirement, required, tries, release, source, block |
| `Domain\Model\Block` | A block of a cmi5 course structure: title, place among the children of its parent, source, parent |
| `Domain\Model\Enrollment` | A learner's enrollment: registration, moment, completion |
| `Domain\Model\Progress` | A learner's progress in an activity |
| `Domain\Model\RetryRequest` | A request for a second chance, its decision and the teacher's note |
| `Domain\Model\LaunchSession` | A cmi5 launch: fetched or not, expiry, state |
| `Domain\Model\ScormAttempt` | A SCORM attempt: number, version, state, session, tracking data, status, total time |
| `Domain\Scorm\AttemptState` | `running`, `paused`, `suspended`, `ended` |
| `Domain\ValueObject\ScormAttemptId` | The identifier of a SCORM attempt |
| `Domain\Model\Assignment` | A course assigned to a learner, a group or a unit: due moment, renewal, who assigned it |
| `Domain\Model\Obligation` | A learner's share of an assignment: cycle, due moment, completion, renewal, reminders sent |
| `Domain\Model\TranscriptEntry` | A completion in a learner's transcript: course and its title, moment, credits, assignment and cycle |
| `Domain\Training\AssigneeKind`, `ObligationState`, `ReminderStage`, `TrainingNotice`, `CreditUnit` | Who a course is assigned to, where a learner stands, the reminders, the notices, the units of credits |
| `Domain\ValueObject\AssignmentId`, `TranscriptEntryId` | The identifiers of an assignment and of a transcript entry |
| `Domain\ValueObject\Credits` | Credits in hundredths, with their unit |
| `Domain\Model\LearningPath`, `PathStep`, `PathEnrollment` | A learning path: title and description; a course at its place in it, required or not, with its prerequisites; a learner's enrollment in it and its completion |
| `Domain\Path\StepState` | `locked`, `open`, `completed` |
| `Domain\Model\EquivalenceGroup`, `EquivalentCourse` | A group of equivalent courses, and a course of it with its language |
| `Domain\Equivalence\LanguageTag` | The shape of a language tag, normalized in lowercase with `-` |
| `Domain\ValueObject\LearningPathId`, `PathEnrollmentId`, `EquivalenceGroupId` | The identifiers of a path, of an enrollment in a path and of a group of equivalent courses |
| `Domain\Model\Catalogue`, `CatalogueEntry` | A catalogue: title, description, shown to everyone or to one unit; a course at its place in it, open to self-enrollment or not, its price and its tags, and the reference that sells it |
| `Domain\Catalogue\CatalogueAudience`, `CatalogueSort`, `PriceFilter`, `OfferedCourse` | `public` or `unit`; `placed`, `title`, `newest`, `price`; `any`, `free`, `paid`; a course as a catalogue offers it, with its group of equivalent courses and its language there |
| `Domain\ValueObject\CatalogueId`, `CoursePrice` | The identifier of a catalogue; a price in minor units of an ISO 4217 currency |
| `Domain\Xapi\XapiVerb` | The verbs of ADL an xAPI activity reports with, and `voided` |
| `Domain\ValueObject\CourseId`, `ModuleId`, `BlockId`, `ActivityId`, `EnrollmentId`, `RetryRequestId`, `LaunchSessionId` | Identifiers, UUID version 7 when the bundle draws them |
| `Domain\ValueObject\LearnerId` | A learner's stable identifier, never an e-mail address |
| `Domain\ValueObject\Release` | At once, on a moment, or days after the enrollment |
| `Domain\ValueObject\Score` | A scaled score, from -1 to 1 |
| `Domain\ValueObject\Outcome` | What a learner did: completed, passed or failed, score |
| `Domain\ValueObject\Availability` | Where an activity stands for a learner, and what is left |
| `Domain\Progress\CompletionRequirement` | The values of cmi5's `moveOn` |
| `Domain\Progress\AvailabilityStatus` | `open`, `satisfied`, `scheduled`, `locked`, `retry_needed`, `retry_requested` |
| `Domain\Progress\RetryDecision` | `pending`, `granted`, `refused`, `waived` |
| `Domain\Course\CourseOrigin` | `native`, `scorm12`, `scorm2004`, `cmi5`, `xapi`; `of()` the format of a package |
| `Domain\Cmi5\Cmi5Verb`, `Cmi5Iri`, `SessionState` | The verbs, IRIs and session states of cmi5; the launch methods are phil/elearning-packages' `LaunchMethod` |
| `Domain\Contract\CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`, `LaunchSessionRepository`, `ScormAttemptRepository`, `AssignmentRepository`, `TranscriptRepository`, `LearningPathRepository`, `EquivalenceRepository`, `CatalogueRepository` | The stores |
| `Domain\Event\ScormSessionEnded` | A session of a SCORM activity ended, and whether its attempt did |
| `Domain\Exception\AttemptRefused` | A commit refused: its token, its learner or its session is not the attempt's |
| `Domain\Event\*` | `LearnerEnrolled`, `ActivityProgressed`, `ActivitySatisfied`, `CourseCompleted`, `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived`, `BlockSatisfied`, `CourseImported`, `StatementRecorded`, `TrainingAssigned`, `ObligationOverdue`, `ObligationRenewed`, `EnrollmentRenewed`, `CreditsGranted`, `LearnerEnrolledInPath`, `PathCompleted` |
| `Domain\Exception\CoursesException` | What every failure of the bundle is |
| `Domain\Exception\EnrollmentRefused` | An enrollment from a catalogue refused, and whether the course is bought instead |
| `Domain\Exception\InvalidCourse`, `UnknownRecord`, `ActivityUnavailable`, `PackageRefused`, `StatementRefused`, `RecordStoreUnavailable` | A value refused, a record missing, an activity not open, a package refused, a statement refused, a statement the learning record store did not keep |
| `Domain\Identifiers` | The shape of identifiers and titles |

## Application

| Class | Role |
|---|---|
| `Application\Courses` | Writes and reads courses, modules, blocks and activities |
| `Application\Enrollments` | Enrollment, progress, waivers, tries, completion |
| `Application\Completions`, `Completion` | Whether a learner completed a course, or an equivalent course, with the enrollment, the course completed and the moment |
| `Application\Equivalences` | The groups of equivalent courses, the course of a language, the equivalents of a course |
| `Application\Path\LearningPaths`, `PathProgress`, `StepStatus` | The learning paths, their steps, the enrollment in them, the progress of a learner and their completion |
| `Application\Path\PathCompletions` | Advances the learner's paths at each `CourseCompleted` |
| `Application\Catalogue\Catalogues`, `CatalogueQuery`, `CataloguePage`, `CatalogueItem` | The catalogues, the courses they place, the catalogues shown to a learner, a page of one as a query lists it with where the learner stands, self-enrollment and the courses bought |
| `Application\Availabilities` | Where each activity stands for a learner |
| `Application\Learning` | Launches an activity and marks it done for the learner |
| `Application\SecondChances`, `RetryReview` | Second chances asked for, granted, refused or waived, and their review |
| `Application\ActivityTypes` | The activity types by name |
| `Application\ActivityType\PageActivity`, `LinkActivity`, `ScormActivity`, `Cmi5Activity`, `XapiActivity` | The adapters of the activity types |
| `Application\Launch`, `LaunchWindow`, `LaunchUrl` | Where the learner goes to take an activity, and the parameters added to its URL |
| `Application\ContentUrls` | The addresses of the files on the content origin |
| `Application\CourseOutline`, `ModuleOutline`, `ActivityStatus` | A course read as an outline, and where an activity stands |
| `Application\Contract\ActivityType`, `CurrentLearner`, `ContentStorage`, `LearningRecordStore`, `Cmi5Endpoints`, `XapiPackageReader`, `AssigneeMembers`, `TrainingNotifier`, `LearnerUnits`, `CourseCheckout`, `TrainingCosts`, `TrainingUnits` | The ports; the packages are opened through the `PackageOpener` of phil/elearning-packages |
| `Application\Import\CourseImporter` | Imports a package or a course structure, read by phil/elearning-packages |
| `Application\Import\ScormPlanner`, `Cmi5Planner`, `XapiPlanner`, `ImportPlan`, `PlannedModule`, `PlannedBlock`, `PlannedActivity` | Turn a manifest into a course to create |
| `Application\Import\XapiPackage`, `XapiLaunchable` | What a reader of xAPI packages found |
| `Application\Import\ImportReport` | What an import created and left out |
| `Application\Import\ResultImporter`, `ScormStatus` | Imported results |
| `Application\Scorm\ScormRuntime` | Opens the sessions of SCORM attempts, keeps their commits, ends them — at their deadline too — and records their outcome; opens an ended attempt for review |
| `Application\Scorm\ScormTokens` | The attempt tokens |
| `Application\Scorm\ScormLaunch`, `CommitAnswer` | What a launch or a review hands the browser, and what a commit answers |
| `Application\Scorm\TimeLimit` | The time an attempt is allowed: the manifest's, the activity's own `time_limit`, or the shorter, and its deadline |
| `Application\Cmi5\Cmi5Launcher` | Launches a unit |
| `Application\Cmi5\Cmi5Tokens` | The fetch URL's answer, and the tokens |
| `Application\Cmi5\Cmi5Statements` | Receives and issues statements |
| `Application\Cmi5\StatementRules` | The rules of cmi5 |
| `Application\Cmi5\PlatformStatements`, `LaunchData`, `Cmi5Context`, `Cmi5Settings` | The platform's statements, the launch data, the context of a launch, the settings |
| `Application\Cmi5\IssueWaivedStatement` | Issues the `waived` statement of a waived unit, then the `satisfied` statements it brings |
| `Application\Xapi\XapiLauncher` | Launches an xAPI activity with the parameters xAPI packages read |
| `Application\Xapi\XapiStatements`, `XapiRules`, `XapiOutcomes`, `XapiContext` | Receives the statements of an xAPI activity, checks them, reads their outcome |
| `Application\Xapi\StatementBatch` | The bounds of the body of a request to the statements resource |
| `Application\Training\Assignments`, `TrainingStatus` | Assigns courses, reaches the members of groups and units, completes the obligations, and where a learner stands |
| `Application\Training\TrainingRun`, `TrainingReport`, `TrainingSettings` | A run of reminders, renewals and new members, and its settings |
| `Application\Training\Recertification` | When a completion is renewed, through phil/recurrence |
| `Application\Training\Transcripts`, `Transcript` | The credits of the courses and the transcripts, as an array and CSV |
| `Application\Training\TrainingCost` | What a completion cost and its eligible part, in minor units, bounded |
| `Application\Training\TrainingCompletions` | Listens to `CourseCompleted`: the obligations, then the transcript |
| `Application\Message\RunTraining`, `Application\Handler\RunTrainingHandler` | The message of the schedule and its handler |
| `Application\Cmi5\Cmi5Satisfaction` | Issues the `satisfied` statements of the blocks and of the course, once for each enrollment; internal, reached through `Cmi5Statements::satisfy()` |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\CourseTables`, `SchemaListener` | The tables, their upgrade, and their place in the schema of the ORM |
| `Infrastructure\Doctrine\DbalCourseRepository`, `DbalEnrollmentRepository`, `DbalRetryRequestRepository`, `DbalLaunchSessionRepository`, `DbalLearningRecordStore`, `Rows` | The DBAL stores |
| `Infrastructure\Certificates\CertificatesCourseCompletions` | The adapter of the `CourseCompletions` port of phil/certificates-bundle, for the courses and, after `path:`, the learning paths |
| `Infrastructure\Doctrine\DbalLearningPathRepository`, `DbalEquivalenceRepository` | The learning paths and the groups of equivalent courses through DBAL |
| `Infrastructure\Doctrine\DbalCatalogueRepository` | The catalogues and their entries through DBAL, the courses offered read in one query |
| `Infrastructure\Http\CatalogueController` | The pages of the catalogues and the enrollment from a catalogue |
| `Infrastructure\Organizations\OrganizationsLearnerUnits` | The units whose catalogues reach a learner, from the tree of units of phil/organizations-bundle |
| `Infrastructure\Organizations\OrganizationsTrainingUnits` | The unit the training of a learner counts in, from the tree of units of phil/organizations-bundle |
| `Infrastructure\Reporting\CoursesTrainingSource` | The completions of the transcripts as the `TrainingSource` of phil/reporting-bundle: their hours from their credits, their costs and units from the ports |
| `Infrastructure\Commerce\CourseSellables`, `CoursePurchases` | The courses sold through phil/commerce-bundle: the sellables of type `course`, and the buyer enrolled at each `OrderPurchased` |
| `Infrastructure\Import\ElearningXapiPackageReader` | The reader of xAPI packages on the `XapiCourseReader` of phil/elearning-packages |
| `Infrastructure\Privacy\PathsPrivacy` | The enrollments in learning paths in the access and erasure requests of phil/privacy-bundle |
| `Infrastructure\Xapi\HttpLearningRecordStore` | The remote xAPI learning record store, through phil/safe-http, for the statements; the state documents stay in `DbalLearningRecordStore` |
| `Infrastructure\Storage\FilesystemContentStorage` | The storage on the filesystem |
| `Infrastructure\Http\CourseController`, `CourseRequest` | The pages and their CSRF check |
| `Infrastructure\Http\Cmi5Controller` | The fetch URL and the xAPI endpoint |
| `Infrastructure\Http\ContentController` | The files on the content origin |
| `Infrastructure\Http\ScormController`, `ScormCommitPayload` | The commit endpoint of the SCORM sessions, and the body it reads |
| `Infrastructure\Http\ScormFrameController` | The frame of the SCORM run time, on the content origin |
| `Infrastructure\Doctrine\DbalScormAttemptRepository` | The SCORM attempts through DBAL |
| `Infrastructure\Doctrine\DbalAssignmentRepository`, `DbalTranscriptRepository` | The assignments, obligations, transcripts and credits through DBAL |
| `Infrastructure\Http\TrainingController` | The learner's training and transcript, the managers' pages |
| `Infrastructure\Notifications\NotificationsTrainingNotifier` | The notices of the training through phil/notifications-bundle, and the types it declares |
| `Infrastructure\Scheduler\TrainingSchedule` | The schedule `phil_courses_training` |
| `Infrastructure\Privacy\TrainingPrivacy` | The obligations and the transcript in the access and erasure requests of phil/privacy-bundle |
| `Infrastructure\Privacy\ScormPrivacy` | The SCORM attempts in the access and erasure requests of phil/privacy-bundle |
| `Infrastructure\Privacy\Cmi5Privacy` | The cmi5 and xAPI launch sessions, statements, state documents and notes of the `satisfied` statements in the access and erasure requests of phil/privacy-bundle, and what a store outside the bundle keeps |
| `Infrastructure\Http\SecondChancesController` | The administration page of the second chances |
| `Infrastructure\Routing\RoutedCmi5Endpoints` | The URLs of the endpoint and the fetch URL |
| `Infrastructure\Console\ImportCommand`, `ImportResultsCommand`, `RetriesCommand`, `AssignCommand`, `CreditsCommand`, `TrainingCommand` | The commands |
| `Infrastructure\Audit\AuditSecondChances` | The trail of the second chances |
| `Infrastructure\Security\SecurityCurrentLearner` | The learner from Symfony Security |
| `Infrastructure\Twig\CoursesExtension`, `KitComponents` | `phil_courses_kit()` and the names of the recipes, `phil_courses_price()` |
| `Infrastructure\DependencyInjection\CoursesServices`, `KitSubdirectoryPass` | The services |

## Commands

| Command | What it does |
|---|---|
| `phil:courses:import <path> [--title=]` | Imports a SCORM, cmi5 or xAPI package, or a cmi5 course structure |
| `phil:courses:import-results <course> <file>` | Imports a CSV export of results |
| `phil:courses:retries [--grant=] [--refuse=] [--waive=] [--by=] [--note=]` | Lists the second chances, or grants, refuses or waives one with the teacher's note |
| `phil:courses:assign <course> <kind> <assignee> <due> [--renew-months=] [--by=]` | Assigns a course to a learner, a group or a unit, due by the end of a day in the site's time zone |
| `phil:courses:credits <course> <amount> [--unit=]` | Sets the credits a course grants, in `ceu` or `hours`; `0` for none |
| `phil:courses:training` | Runs the reminders, the renewals and the new members of groups and units now |
