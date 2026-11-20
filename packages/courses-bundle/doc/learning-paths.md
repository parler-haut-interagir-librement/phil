# Learning paths

A learning path is an ordered sequence of courses: onboarding in four courses, a certification in six. Each course of
the path is a step, required or not, which waits for courses placed before it. A learner enrolls in the path once, and
the bundle enrolls them in each course as its prerequisites are completed. The path is completed once every required
step is.

## Defining a path

`LearningPaths` writes and reads the paths:

```php
use Phil\CoursesBundle\Application\Path\LearningPaths;

$path = $paths->create('Welding', 'From the first bead to the final project.');

$paths->addStep($path->id, $basics->id);
$paths->addStep($path->id, $tools->id, required: false);
$paths->addStep($path->id, $safety->id, prerequisites: [$basics->id]);
$paths->addStep($path->id, $project->id, prerequisites: [$basics->id, $safety->id]);

// A step goes before another one rather than at the end.
$paths->addStep($path->id, $welcome->id, before: $basics->id);

$paths->removeStep($path->id, $tools->id);
$paths->rename($path->id, 'Arc welding', 'Arcs, beads and joints.');
$paths->remove($path->id);
```

| Rule | Refusal |
|---|---|
| A course is a step of a path once | `InvalidCourse`: `The course "…" is already a step of the path.` |
| A prerequisite is a step placed before the new one, so that the prerequisites hold no cycle | `InvalidCourse`: `The prerequisite "…" is not a step placed before this one.` |
| A step waits for 50 courses at most, each once, never itself | `InvalidCourse` |
| A path holds 100 steps at most | `InvalidCourse`: `A path holds 100 steps at most.` |
| The path and the course exist | `UnknownRecord` |

The steps keep their order; `steps()` gives them as `PathStep` — the course, its place from 0, whether it is required,
and its prerequisites. A step removed is taken out of the prerequisites of the others. Removing a path removes its
steps and its enrollments; the learners stay enrolled in its courses. Removing a course removes its steps in every path;
a prerequisite that is no step any more holds nothing back.

## Enrolling and advancing

```php
$enrollment = $paths->enroll($path->id, $learner);
```

`enroll()` keeps a `PathEnrollment` — once per learner and path; a second call gives back the first — dispatches
`LearnerEnrolledInPath`, then enrolls the learner in each course whose prerequisites they completed. Each later
completion of a course advances the learner's open enrollments in the paths that hold it: `PathCompletions` listens to
`CourseCompleted`, enrolls the learner in the courses the completion opens, and completes the path once every required
step is completed. A course completed before the enrollment counts. The prerequisites gate the enrollment the path
makes; a site that lets learners enroll themselves in a course leaves that door open.

`progress()` gives a `PathProgress`: the enrollment, each step as a `StepStatus` with its state — `locked`, `open` or
`completed` — and the completion that completed it, and the share of the required steps completed, from 0 to 100.

```php
foreach ($paths->progress($enrollment)->steps as $status) {
    echo $status->step->course->value, ' ', $status->state->value, \PHP_EOL;
}
```

A step is completed by its course or by an equivalent course of its [group](equivalent-courses.md): the completion
then names the course the learner took. A path without steps is never completed.

## Certificates and badges

`PathCompleted` is dispatched once, when the path is completed, with the enrollment, the path and the learner. An
application issues a badge from it with a listener of its own, as it does from `CourseCompleted` for a course.

With phil/certificates-bundle, a certificate course names a path as its completion source by `path:` followed by the
path's identifier:

```php
$courses->define('welding', 'Welding', 'The path of the welders.', 'Complete every course of the path.', completionSource: 'path:'.$path->id->value);
```

The bundle answers the port with the identifier of the learner's enrollment in the completed path, which the
certificate keeps as its evidence. `LearningPaths::completion()` answers the same for another part of the application.

## Events

| Event | Carries |
|---|---|
| `LearnerEnrolledInPath` | the enrollment in the path, the path, the learner |
| `PathCompleted` | the enrollment in the path, the path, the learner |

## Privacy

With phil/privacy-bundle, `PathsPrivacy` gives a learner's enrollments in paths — the path, when it began, when it was
completed — under the module `courses_paths`, and deletes them on an erasure. The enrollments in the courses themselves
belong to the other modules of the bundle.

## Pages

The bundle draws no page for the paths yet: the application lists them, enrolls a learner and shows their progress
with `LearningPaths`, each course leading to its outline at the route `phil_courses_outline`.
