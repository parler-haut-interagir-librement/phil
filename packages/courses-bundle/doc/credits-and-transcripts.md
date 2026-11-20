# Credits and transcripts

A course can grant credits — continuing education units or hours — on its completion. Each completion enters the
learner's transcript, with the credits the course grants at that moment and the assignment it fulfilled, and the
transcript adds the credits up by unit. The transcript is a page, JSON and CSV, for the learner and for the managers.

## Credits

```php
$transcripts->credit($course->id, Credits::of('1.5', CreditUnit::Ceu));   // CreditUnit::Ceu or CreditUnit::Hours
$transcripts->credit($course->id, null);                                  // none
```

```sh
php bin/console phil:courses:credits <course> 1.5 --unit=ceu
php bin/console phil:courses:credits <course> 0
```

An amount has two decimals at most, from 0.01 to 9 999.99, kept as hundredths so that sums stay exact. A change
applies to the completions that follow; those entered before keep their credits.

## The transcript

`TrainingCompletions` listens to `CourseCompleted`: it completes the learner's obligations under the course, then
enters the completion in their transcript — the course, its title at that moment, so that the line stays readable once
the course is renamed or removed, the moment, the credits, and the assignment and cycle it fulfilled. A recertified
course completed again enters it again. `CreditsGranted` is dispatched with each entry.

`Transcripts::of(LearnerId $learner)` gives a `Transcript`: its entries, the latest first; `total(CreditUnit $unit)`,
the hundredths of that unit; `toArray()`; and `csv()`.

| Route | Path with the recipe | Who | What |
|---|---|---|---|
| `phil_courses_transcript` | `/courses/transcript` | the learner signed in | Their transcript as a page, with the totals |
| `phil_courses_transcript` | `/courses/transcript.json` | the learner signed in | The same as JSON |
| `phil_courses_transcript` | `/courses/transcript.csv` | the learner signed in | The same as CSV, an attachment |
| `phil_courses_admin_transcript` | `/admin/courses/transcripts/{learner}`, `/json`, `/csv` | a manager | Any learner's transcript |

The JSON holds the learner, the entries and the totals:

```json
{
    "learner": "member-42",
    "entries": [
        {"course": "01J…", "title": "Fire safety", "completed_at": "2026-11-22T10:00:00+00:00", "credits": "1.5", "unit": "ceu", "assignment": "01J…", "cycle": 1}
    ],
    "totals": {"ceu": "1.5"}
}
```

The CSV follows RFC 4180, with the columns `course`, `title`, `completed_at`, `credits`, `unit`, `assignment` and
`cycle`. A cell a spreadsheet would read as a formula — starting with `=`, `+`, `-`, `@`, a tab or a carriage return —
is prefixed with a quote. Every answer is `no-store`; the CSV is sent as `text/csv` with `nosniff`.

## Privacy

With phil/privacy-bundle installed, the module `courses_training` gives in an access request the learner's obligations
— the assignment, the course, the cycle, the moments of the assignment, the due date, the completion and the renewal,
and the reminders sent — and their transcript with its totals.

An erasure deletes the obligations and takes the person's name off the assignments they made, which stay unsigned. The
transcript is deleted too, unless the application keeps transcripts under a legal obligation, as continuing education
often asks: with `phil_courses.training.keep_transcripts: true`, the transcript survives and the report says so.

| Store | Erasure |
|---|---|
| `phil_course_obligation` | `deleted` |
| `phil_course_assignment` | `redacted`: the name of who assigned is removed |
| `phil_course_transcript` | `deleted`, or `survives` with `keep_transcripts` |

The application declares the transcripts in its retention schedule, with the period its obligation sets:

```php
new RetentionRule(
    'courses.transcripts',
    'phil_course_transcript',
    [DataCategory::fromString('education')],
    RetentionPeriod::fixed('P7Y'),
    Mechanism::Manual,
    ErasureEffect::Survives,
    AccessResponse::Returned,
    new Processing('Continuing education records the law asks the organization to keep.'),
);
```

The transcript names the learner by the stable identifier the courses know them by, never by a name or an e-mail
address; the CSV and JSON carry nothing more.
