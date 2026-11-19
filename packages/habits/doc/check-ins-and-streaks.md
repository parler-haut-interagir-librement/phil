# Check-ins and streaks

## Checking in

`Habits::checkIn($id, $status, $value, $day)` records what the member did on a day — today when no day is given —, in
place of what was recorded on that day: a habit holds at most one check-in per day.

| Status | Means | Value |
|---|---|---|
| `CheckInStatus::Done` | Done, the whole target when the habit has one. | Optional, for the record, on a habit with a target. |
| `CheckInStatus::Partial` | Done in part, towards the habit's numeric target. | Required: the amount done. Only a habit with a target takes it. |
| `CheckInStatus::Skipped` | Skipped on purpose. | None. |
| `CheckInStatus::Frozen` | Frozen ahead of time: a trip, an illness, a rest day. | None. |

A habit with a target — 20 pages, 8 glasses — counts its days in units: a done day brings the whole target, a partial
one its value up to the target. A habit without one counts check-ins.

A check-in is refused with `InvalidCheckIn` when its day is ahead of today, before the habit's first day, further back
than the days `Habits` allows — seven by default —, or when the habit is archived.

`checkIn()` returns the habit's `Progress`, and dispatches its [events](events.md).

## Going back, freezing, taking back

- **Going back.** A member who forgot yesterday checks it in with `day: $yesterday`, within the days allowed.
- **Freezing.** `freeze($id, $from, $through)` freezes a run of days, up to a year ahead and 366 days long; days already
  done, in full or in part, stay as they are. It returns the number of days frozen.
- **Taking back.** `undo($id, $day)` forgets what was recorded on a day, a frozen day ahead included.

## Streaks

`Progress::$streak` holds the current streak, the best one and the unit they count in — days, windows, weeks or months,
by schedule. Each period over is one of three things:

| A period | When | The streak |
|---|---|---|
| Met | Its check-ins bring the units it asks. | Adds one. |
| Excused | It is short, but its skipped and frozen days, counted as done, would have met it. | Stays. |
| Missed | Anything else. | Back to zero; the best stays. |

The period that holds today is met as soon as it is, and otherwise waits without breaking anything: a member who has
not yet read today keeps their streak until the day is over.

A day done in part but short of the target does not meet a daily period; it still counts in the strength, which is the
number made to reward effort short of the mark.

## Where a habit stands

`Habits::progress($id)` gives the `Progress` of a habit today: its streaks, its strength from 0 to 1 —
`strengthPercent()` for the whole percentage shown —, and `current`, the `PeriodProgress` of the period that holds
today, with the units done, the units it asks and its excused days, for a line such as "2 of 3 this week". An archived
habit's progress stops on its last day.

`history($id, $from, $through)` lists the check-ins of a range, the earliest first, for a calendar view.
