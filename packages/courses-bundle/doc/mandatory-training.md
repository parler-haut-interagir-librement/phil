# Mandatory training

A course can be assigned: to a learner, to the members of a group, or to the members of a unit of an organization,
due by a moment, and renewed after a number of months for a recertification. Each learner reached is enrolled and
given an obligation, reminded before, on and after the due moment, and shown overdue to themselves and to the
managers. The part is enabled by default; `phil_courses.training.enabled: false` leaves it out.

## Assigning a course

```php
$assignments->assign(
    $course->id,
    AssigneeKind::Learner,          // Learner, Group or Unit
    'member-42',                    // the identifier of the learner, the group or the unit
    new DateTimeImmutable('2026-12-01 00:00', new DateTimeZone('America/Toronto')),
    12,                             // renewed 12 months after each completion; null for a course taken once
    LearnerId::of('manager-7'),     // who assigns it, told once it is overdue
);
```

```sh
php bin/console phil:courses:assign <course> learner member-42 2026-11-30 --renew-months=12 --by=manager-7
```

The due moment is a deadline: the command reads its date as the last day, in the site's time zone, and the course is
due before the midnight that ends it. The assignment is refused when the course is unknown, the due moment before the
assignment, the renewal outside 1 to 120 months, or the assignee not an identifier.

Every member reached is enrolled in the course and gets an obligation for the first cycle, due at the assignment's due
moment, and is told the course is assigned. A member who completed the course already starts completed, and their
renewal counts from that completion.

### Groups and units

The bundle keeps no groups nor organizations. The members of a group or a unit come from an `AssigneeMembers` service
of the application — autoconfigured, tag `phil_courses.assignee_members` —; the first that supports the kind answers.

```php
final readonly class TeamMembers implements AssigneeMembers
{
    public function __construct(private TeamRepository $teams) {}

    public function supports(AssigneeKind $kind): bool
    {
        return AssigneeKind::Group === $kind;
    }

    public function members(AssigneeKind $kind, string $assignee): iterable
    {
        foreach ($this->teams->members($assignee) as $member) {
            yield $member->id->toRfc4122();
        }
    }
}
```

Without an adapter for its kind, the assignment is refused. An assignment reaches `phil_courses.training.max_members`
members at most, 10 000 by default; a group larger is refused. Each run gives an obligation to the members who joined
since; a member who left keeps theirs.

## Where a learner stands

| State | When |
|---|---|
| `pending` | Assigned, before the reminder window. |
| `due_soon` | Within `reminder_days` days of the due moment, 7 by default. |
| `overdue` | The due moment passed, the course not completed in this cycle. |
| `completed` | Completed in this cycle. |

The learner sees their assigned courses on the page `phil_courses_training` (`/courses/training` with the recipe),
the earliest due first, each with its due date and its state — overdue in the destructive variant of the recipe
`Badge` —, and a link to their [transcript](credits-and-transcripts.md).

A manager — who holds `phil_courses.training.manager_role`, `ROLE_COURSES_MANAGER` by default — sees on the page
`phil_courses_admin_training` (`/admin/courses/training`) the obligations of every learner overdue or due soon, the
earliest due first, each with a link to that learner's transcript. Without an authorization checker, the page is
refused to everyone.

## Reminders

Each run reminds every learner whose obligation is not completed, once a cycle for each stage:

| Stage | When | Notification |
|---|---|---|
| Before the due moment | within `reminder_days` days of it | `courses.training.due_soon` |
| On the due moment | in its last day | `courses.training.due` |
| Overdue | once it passed | `courses.training.overdue`, and `courses.training.overdue_manager` to who assigned it |

A run that first reaches an obligation late sends the stage of that moment only. `ObligationOverdue` is dispatched
with the overdue reminder.

With phil/notifications-bundle registered, the bundle declares the types `courses.training.assigned`, `due_soon`,
`due`, `overdue`, `overdue_manager` and `renewed` — on the site's centre and by e-mail, the overdue ones of high
importance; an application that declares a type of the same key changes it — and sends them through its
`Notifications` service, about the course, with identifiers only: the assignment, the learner, the cycle and the due
date, `YYYY-MM-DD` in UTC. Their English and French templates are in the `phil_notifications` domain of the bundle. An
application that tells its people otherwise names its own `TrainingNotifier` in `phil_courses.training.notifier`.

## The run

`TrainingRun` brings the groups and units up to date, sends the reminders and renews the recertifications. The
obligations are read a batch of 500 at a time, so that a run holds a bounded number in memory, and every service keeps
no state between runs.

With symfony/scheduler and symfony/messenger, the schedule `phil_courses_training` sends `RunTraining` — a message that
carries nothing — every day at `phil_courses.training.run_at`, `06:00` by default, in the site's time zone:

```sh
php bin/console messenger:consume scheduler_phil_courses_training
```

`phil_courses.training.schedule: false` leaves the schedule out; `phil:courses:training` runs it from cron instead, and
says how many members it reached, reminders it sent and completions it renewed, and which assignments it skipped
because their members could not be named.

## Recertification

An assignment with `renew_months` renews each completion: the completion is renewed at the next occurrence of a
monthly rule of that interval from the completion, read by phil/recurrence — the same day of the month, at the same
time; a completion on a day a month lacks, the 31st, is renewed at the next month that has it, as RFC 5545 counts a
monthly rule.

When the renewal comes, the run opens the next cycle: due `renewal_grace_days` days after the renewal, 30 by default,
its reminders sent again. The learner's enrollment starts again — its progress in every activity forgotten, its
`satisfied` statements free to be issued again, its completion undone, its registration kept — and they are told with
`courses.training.renewed`. `EnrollmentRenewed` and `ObligationRenewed` are dispatched. The transcript keeps every
completion.

## Events

| Event | Carries |
|---|---|
| `TrainingAssigned` | the assignment, the course |
| `ObligationOverdue` | the assignment, the learner, the course |
| `ObligationRenewed` | the assignment, the learner, the course |
| `EnrollmentRenewed` | the enrollment, the course, the learner |
| `CreditsGranted` | the transcript entry, the learner, the course |

## Configuration

| Option | Default | What it does |
|---|---|---|
| `training.enabled` | `true` | The mandatory training, its pages, commands, schedule and privacy module. |
| `training.reminder_days` | 7 | How many days before the due moment a course is due soon and its first reminder sent, 1 to 90. |
| `training.renewal_grace_days` | 30 | How many days after its renewal a recertification is due again, 1 to 365. |
| `training.max_members` | 10 000 | How many members of a group or unit an assignment reaches. |
| `training.run_at` | `06:00` | The time of the daily run of the schedule, `HH:MM` in the site's time zone. |
| `training.schedule` | `true` | Whether the schedule `phil_courses_training` runs, with Symfony Scheduler. |
| `training.manager_role` | `ROLE_COURSES_MANAGER` | The role a manager holds to see the training of every learner and their transcripts. |
| `training.notifier` | `null` | A `TrainingNotifier` service; the notifications of phil/notifications-bundle when null and it is registered, none otherwise. |
| `training.keep_transcripts` | `false` | Whether the transcripts survive an erasure request; see [credits and transcripts](credits-and-transcripts.md#privacy). |
