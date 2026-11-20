# The course model

## Courses, modules and activities

A **course** has a title, a description, an origin — written in the application, or imported from a SCORM 1.2,
SCORM 2004, cmi5 or xAPI package, whose identifier it keeps as its source — and whether its activities must be taken
in order. It holds **modules** in order, up to 500, and each module holds **activities** in order, up to 500.

`Courses` writes and reads them:

```php
$course = $courses->create('Workshop safety', 'Tools and habits.', ordered: true);
$basics = $courses->addModule($course->id, 'Basics');
$week2 = $courses->addModule($course->id, 'Week 2', Release::daysAfterEnrollment(7));

$courses->addActivity($basics->id, 'Read me first', 'page', ['body' => 'Wear goggles.']);
$courses->addActivity($basics->id, 'The manual', 'link', ['url' => 'https://example.org/manual']);
$courses->addActivity($week2->id, 'Final check', 'page', [], CompletionRequirement::Passed, required: true, maxAttempts: 2);

$outline = $courses->outline($course->id); // the course, its modules and their activities
```

An activity has a **type**, which names the adapter that checks its settings and launches it:

| Type | Settings | Opens | Marked done by |
|---|---|---|---|
| `page` | `body`, plain text | On the activity page | The learner |
| `link` | `url`, an absolute address | In its own window | The learner |
| `scorm` | `path` and `query` of its launch file, `scorm_version`, `time_limit` — its own time limit, in seconds —, and what the import read of the manifest | Framed, from the content origin, under the SCORM run time | Its content, through the run time; the learner when the run time is off |
| `cmi5` | `au_id`, `url` or `path` and `query`, `launch_method`, `mastery_score`, `launch_parameters`, `entitlement_key` | Framed, or in its own window when it asks to | Its own statements only |
| `xapi` | `activity_id`, `url` or `path` and `query`, `launch_method`, `mastery_score` | Framed, or in its own window when it asks to | Its own `completed`, `passed` and `failed` statements only; see [xAPI activities](xapi-activities.md) |

An application adds its own types: see [Extension points](extension-points.md).

## Blocks

A course imported from cmi5 also keeps the **blocks** of its course structure as a tree. A `Block` has a title, the
identifier the course structure gives it as its source, the block it is nested in — none at the first level — and its
place among the children of that parent, blocks and units counted together. Each activity of the course names the
innermost block that holds it, in `Activity::$block`; a unit of the first level names none. The modules stay the
outline the learner sees; the blocks are what the `satisfied` statements of cmi5 follow — see [cmi5](cmi5.md).

```php
$charts = $courses->addBlock($course->id, 'Charts', 'https://example.org/blocks/charts', 0);
$tides = $courses->addBlock($course->id, 'Tides', 'https://example.org/blocks/tides', 1, $charts->id);
$courses->addActivity($module->id, 'Tide tables', 'cmi5', $settings, block: $tides->id);

$courses->blocks($course->id); // each parent before the blocks it holds, siblings in their order
```

## What satisfies an activity

Each activity carries a **requirement**, the values of cmi5's `moveOn`, whatever its type:

| Requirement | Satisfied when |
|---|---|
| `Completed` | the learner completed it |
| `Passed` | the learner passed it |
| `CompletedAndPassed` | both |
| `CompletedOrPassed` | either |
| `NotApplicable` | the learner opened it |

An activity is **required** or not; the course is completed when every required activity is satisfied. A waived
activity counts as satisfied.

## Enrollments and progress

`Enrollments::enroll()` enrolls a learner once in a course; enrolling again gives back the same enrollment. Each
enrollment has a registration, a UUID that ties its cmi5 statements together. The learner is named by the stable
identifier of their account, never an e-mail address.

The progress of an enrollment in an activity keeps when it started, whether it is completed, passed or failed, whether
it is waived, the best score, the failed tries, the second chances granted, and when it was satisfied. What the learner
reached is kept: a failure after a pass leaves the activity passed, a lower score leaves the best one.

```php
$enrollment = $enrollments->enroll($course->id, LearnerId::of('m-1042'));
$enrollments->record($enrollment, $activity, Outcome::passed(Score::scaled(0.85)));
$enrollments->percent($enrollment); // the share of required activities satisfied, from 0 to 100
```

On the pages, `Learning::launch()` and `Learning::complete()` do the same for the learner signed in, once the
activity is open to them.

## Releases

A module or an activity is released at once, on a date, or a number of days after the enrollment:

```php
Release::immediately();
Release::on(new DateTimeImmutable('2026-12-01 09:00', new DateTimeZone('America/Toronto')));
Release::daysAfterEnrollment(3);
```

Days are counted from midnight of the enrollment day in the site's time zone, `phil_courses.timezone`: a learner who
enrolls at 22:30 on the 12th sees "day 3" open at midnight of the 15th. An activity opens once both its module and
itself are released.

## Where an activity stands

`Availabilities::outline()` says, for each activity of the learner's course, where it stands:

| State | Meaning | What the learner can do |
|---|---|---|
| `open` | Released, not satisfied | Launch it |
| `satisfied` | Done as its requirement asks | Revisit it |
| `scheduled` | Released later; the moment is given | Wait |
| `locked` | In a course of imposed order, an earlier required activity is not satisfied; it is named | Satisfy that one first |
| `retry_needed` | Every try was failed | Ask for a second chance |
| `retry_requested` | A second chance is asked for | Wait for the teacher |

No state is a dead end: a scheduled activity opens, a locked one opens with the activity before it, and a learner out
of tries asks for a second chance, or a teacher waives the activity.

## Second chances

An activity may allow a number of tries; a failure counts one. A learner who failed them all asks for a second chance
with a reason. A teacher decides it once, with a note of 2 000 characters at most: they grant it, which gives one more
try, refuse it, or waive the activity instead, which the course then counts satisfied — a cmi5 unit has its `waived`
statement:

```php
$request = $secondChances->request($enrollment, $activity->id, 'I was ill on the day.');
$secondChances->grant($request->id, 'teacher-7', 'One more try, on Friday.');
$secondChances->refuse($request->id, 'teacher-7', 'The exam is closed.');
$secondChances->waive($request->id, 'teacher-7', 'Done at the workshop.');
$enrollments->waive($enrollment, $activity); // a waiver without a request
```

`SecondChances::review()` gives the waiting requests, the oldest first, each as a `RetryReview` with its enrollment,
course, activity, and the learner's progress: `failures()` and `tries()`, the activity's tries and the second chances
granted, null for no limit.

`phil:courses:retries` lists the waiting requests, and decides one with `--grant`, `--refuse` or `--waive`, `--by` and
an optional `--note`. With phil/audit-bundle registered, each request, grant, refusal and waiver is written to its
trail, with identifiers only: never the reason nor the note, which may hold personal words.

### The administration page

The routes of `config/admin_routes.php`, imported by the application under the prefix of its choice and behind its
firewall, give the teachers a page of the waiting requests:

```yaml
# config/routes/phil_courses.yaml
phil_courses_admin:
    resource: '@PhilCoursesBundle/config/admin_routes.php'
    prefix: /admin/courses
```

| Route | Page |
|---|---|
| `phil_courses_admin_retries` | `GET /second-chances`: each waiting request with the course, the activity, the learner, when they asked, the tries failed and the reason |
| `phil_courses_admin_retry_decide` | `POST /second-chances/{retry}`: grants, refuses or waives, with the note |

The page asks for the role `phil_courses.admin.role` — `ROLE_COURSES_TEACHER` by default — through Symfony Security's
authorization checker, and refuses everyone without one. The decision is made in the name of the teacher signed in,
as the `CurrentLearner` service names them. The form carries the CSRF token `phil_courses`; a missing token, a decision
other than `grant`, `refuse` or `waive`, a request decided already or a note too long answers `400`, an unknown request
`404`. The page draws the recipes `Badge`, `Progress`, `Textarea` and `Button` of the kit, in English and French, and
answers `Cache-Control: no-store`.

## Events

The bundle dispatches these events through the PSR-14 dispatcher; each carries identifiers only.

| Event | When |
|---|---|
| `LearnerEnrolled` | A learner enrolled in a course |
| `ActivityProgressed` | A learner's progress in an activity changed |
| `ActivitySatisfied` | An activity became satisfied, by its requirement or by a waiver |
| `CourseCompleted` | A learner satisfied every required activity of a course; `Completions` answers the same afterwards |
| `RetryRequested`, `RetryGranted`, `RetryRefused`, `RetryWaived` | A second chance was asked for, granted or refused, or its activity waived |
| `BlockSatisfied` | The platform issued the `satisfied` statement of a block of a cmi5 course for an enrollment |
| `CourseImported` | A course was imported from a package |
| `StatementRecorded` | A cmi5 or xAPI statement was accepted or issued, and handed to the learning record store |
| `TrainingAssigned`, `ObligationOverdue`, `ObligationRenewed`, `EnrollmentRenewed`, `CreditsGranted` | The mandatory training: see [mandatory training](mandatory-training.md#events) |

## The pages

| Route | Page |
|---|---|
| `phil_courses_outline` | The outline: the learner's progress, each module and its activities with where each stands |
| `phil_courses_enroll` | The enrollment, when `self_enrollment` is on |
| `phil_courses_launch` | The activity page: the text of a page, the content framed from the content origin, or the link to its window |
| `phil_courses_complete` | Marks an activity done, when its type lets the learner |
| `phil_courses_retry` | Asks for a second chance |
| `phil_courses_training` | The learner's assigned courses and where they stand; see [mandatory training](mandatory-training.md) |
| `phil_courses_transcript` | The learner's transcript, as a page, JSON or CSV; see [credits and transcripts](credits-and-transcripts.md) |

Every form carries the stateless CSRF token `phil_courses`, and a request the browser says came from another site is
refused. The pages answer `Cache-Control: no-store`.
