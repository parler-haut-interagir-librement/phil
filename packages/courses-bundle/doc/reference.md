# Reference

## Bundle

| Class or file | Role |
|---|---|
| `PhilCoursesBundle` | The bundle: its configuration `phil_courses` and the stateless CSRF token `phil_courses` |
| `config/routes.php` | The routes of the pages |
| `config/cmi5_routes.php` | The fetch URL and the xAPI endpoint |
| `config/content_routes.php` | The files of the imported packages and the frame of the SCORM run time, on the content origin |
| `config/admin_routes.php` | The administration page of the second chances |
| `templates/` | `layout`, `outline`, `activity` and `second_chances`, drawn with the recipes of the kit |
| `translations/` | The words of the pages, in English and French |
| `assets/src/scorm_runtime_controller.js` | The Stimulus controller of the activity page of a SCORM session: frames the frame, sends the commits |
| `assets/src/scorm_frame.js`, `assets/src/lib/api.js`, `assets/src/lib/cmi.js` | The frame of the run time on the content origin, the run-time API, and the data model in the browser |
| `recipe/0.1` | The Flex recipe: the bundle, its configuration and its routes |

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
| `Domain\ValueObject\CourseId`, `ModuleId`, `BlockId`, `ActivityId`, `EnrollmentId`, `RetryRequestId`, `LaunchSessionId` | Identifiers, UUID version 7 when the bundle draws them |
| `Domain\ValueObject\LearnerId` | A learner's stable identifier, never an e-mail address |
| `Domain\ValueObject\Release` | At once, on a moment, or days after the enrollment |
| `Domain\ValueObject\Score` | A scaled score, from -1 to 1 |
| `Domain\ValueObject\Outcome` | What a learner did: completed, passed or failed, score |
| `Domain\ValueObject\Availability` | Where an activity stands for a learner, and what is left |
| `Domain\Progress\CompletionRequirement` | The values of cmi5's `moveOn` |
| `Domain\Progress\AvailabilityStatus` | `open`, `satisfied`, `scheduled`, `locked`, `retry_needed`, `retry_requested` |
| `Domain\Progress\RetryDecision` | `pending`, `granted`, `refused`, `waived` |
| `Domain\Course\CourseOrigin` | `native`, `scorm12`, `scorm2004`, `cmi5`; `of()` the format of a package |
| `Domain\Cmi5\Cmi5Verb`, `Cmi5Iri`, `SessionState` | The verbs, IRIs and session states of cmi5; the launch methods are phil/elearning-packages' `LaunchMethod` |
| `Domain\Contract\CourseRepository`, `EnrollmentRepository`, `RetryRequestRepository`, `LaunchSessionRepository`, `ScormAttemptRepository` | The stores |
| `Domain\Event\ScormSessionEnded` | A session of a SCORM activity ended, and whether its attempt did |
| `Domain\Exception\AttemptRefused` | A commit refused: its token, its learner or its session is not the attempt's |
| `Domain\Event\*` | `LearnerEnrolled`, `ActivityProgressed`, `ActivitySatisfied`, `CourseCompleted`, `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived`, `BlockSatisfied`, `CourseImported`, `StatementRecorded` |
| `Domain\Exception\CoursesException` | What every failure of the bundle is |
| `Domain\Exception\InvalidCourse`, `UnknownRecord`, `ActivityUnavailable`, `PackageRefused`, `StatementRefused`, `RecordStoreUnavailable` | A value refused, a record missing, an activity not open, a package refused, a statement refused, a statement the learning record store did not keep |
| `Domain\Identifiers` | The shape of identifiers and titles |

## Application

| Class | Role |
|---|---|
| `Application\Courses` | Writes and reads courses, modules, blocks and activities |
| `Application\Enrollments` | Enrollment, progress, waivers, tries, completion |
| `Application\Completions`, `Completion` | Whether a learner completed a course, with the enrollment and the moment |
| `Application\Availabilities` | Where each activity stands for a learner |
| `Application\Learning` | Launches an activity and marks it done for the learner |
| `Application\SecondChances`, `RetryReview` | Second chances asked for, granted, refused or waived, and their review |
| `Application\ActivityTypes` | The activity types by name |
| `Application\ActivityType\PageActivity`, `LinkActivity`, `ScormActivity`, `Cmi5Activity` | The adapters of the activity types |
| `Application\Launch`, `LaunchWindow` | Where the learner goes to take an activity |
| `Application\ContentUrls` | The addresses of the files on the content origin |
| `Application\CourseOutline`, `ModuleOutline`, `ActivityStatus` | A course read as an outline, and where an activity stands |
| `Application\Contract\ActivityType`, `CurrentLearner`, `ContentStorage`, `LearningRecordStore`, `Cmi5Endpoints` | The ports; the packages are opened through the `PackageOpener` of phil/elearning-packages |
| `Application\Import\CourseImporter` | Imports a package or a course structure, read by phil/elearning-packages |
| `Application\Import\ScormPlanner`, `Cmi5Planner`, `ImportPlan`, `PlannedModule`, `PlannedBlock`, `PlannedActivity` | Turn a manifest into a course to create |
| `Application\Import\ImportReport` | What an import created and left out |
| `Application\Import\ResultImporter`, `ScormStatus` | Imported results |
| `Application\Scorm\ScormRuntime` | Opens the sessions of SCORM attempts, keeps their commits, ends them and records their outcome |
| `Application\Scorm\ScormTokens` | The attempt tokens |
| `Application\Scorm\ScormLaunch`, `CommitAnswer` | What a launch hands the browser, and what a commit answers |
| `Application\Cmi5\Cmi5Launcher` | Launches a unit |
| `Application\Cmi5\Cmi5Tokens` | The fetch URL's answer, and the tokens |
| `Application\Cmi5\Cmi5Statements` | Receives and issues statements |
| `Application\Cmi5\StatementRules` | The rules of cmi5 |
| `Application\Cmi5\PlatformStatements`, `LaunchData`, `Cmi5Context`, `Cmi5Settings` | The platform's statements, the launch data, the context of a launch, the settings |
| `Application\Cmi5\IssueWaivedStatement` | Issues the `waived` statement of a waived unit, then the `satisfied` statements it brings |
| `Application\Cmi5\Cmi5Satisfaction` | Issues the `satisfied` statements of the blocks and of the course, once for each enrollment; internal, reached through `Cmi5Statements::satisfy()` |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Doctrine\CourseTables`, `SchemaListener` | The tables, their upgrade, and their place in the schema of the ORM |
| `Infrastructure\Doctrine\DbalCourseRepository`, `DbalEnrollmentRepository`, `DbalRetryRequestRepository`, `DbalLaunchSessionRepository`, `DbalLearningRecordStore`, `Rows` | The DBAL stores |
| `Infrastructure\Certificates\CertificatesCourseCompletions` | The adapter of the `CourseCompletions` port of phil/certificates-bundle |
| `Infrastructure\Xapi\HttpLearningRecordStore` | The remote xAPI learning record store, through phil/safe-http, for the statements; the state documents stay in `DbalLearningRecordStore` |
| `Infrastructure\Storage\FilesystemContentStorage` | The storage on the filesystem |
| `Infrastructure\Http\CourseController`, `CourseRequest` | The pages and their CSRF check |
| `Infrastructure\Http\Cmi5Controller` | The fetch URL and the xAPI endpoint |
| `Infrastructure\Http\ContentController` | The files on the content origin |
| `Infrastructure\Http\ScormController`, `ScormCommitPayload` | The commit endpoint of the SCORM sessions, and the body it reads |
| `Infrastructure\Http\ScormFrameController` | The frame of the SCORM run time, on the content origin |
| `Infrastructure\Doctrine\DbalScormAttemptRepository` | The SCORM attempts through DBAL |
| `Infrastructure\Privacy\ScormPrivacy` | The SCORM attempts in the access and erasure requests of phil/privacy-bundle |
| `Infrastructure\Http\SecondChancesController` | The administration page of the second chances |
| `Infrastructure\Routing\RoutedCmi5Endpoints` | The URLs of the endpoint and the fetch URL |
| `Infrastructure\Console\ImportCommand`, `ImportResultsCommand`, `RetriesCommand` | The commands |
| `Infrastructure\Audit\AuditSecondChances` | The trail of the second chances |
| `Infrastructure\Security\SecurityCurrentLearner` | The learner from Symfony Security |
| `Infrastructure\Twig\CoursesExtension`, `KitComponents` | `phil_courses_kit()` and the names of the recipes |
| `Infrastructure\DependencyInjection\CoursesServices`, `KitSubdirectoryPass` | The services |

## Commands

| Command | What it does |
|---|---|
| `phil:courses:import <path> [--title=]` | Imports a SCORM or cmi5 package, or a cmi5 course structure |
| `phil:courses:import-results <course> <file>` | Imports a CSV export of results |
| `phil:courses:retries [--grant=] [--refuse=] [--waive=] [--by=] [--note=]` | Lists the second chances, or grants, refuses or waives one with the teacher's note |
