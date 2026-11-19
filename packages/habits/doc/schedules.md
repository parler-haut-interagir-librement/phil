# Schedules

A schedule says when a habit is due. It cuts the habit's days, from its first one, into periods, each asking a number
of check-ins. The streak counts periods; the strength moves once per period.

## The kinds

| Kind | Rule | Builder | Periods | A streak counts |
|---|---|---|---|---|
| Every day | `daily` | `Schedule::daily()` | Each day, one check-in. | Days |
| Once in every N days | `every 3 days` | `Schedule::everyDays(3)` | Each window of N days from the habit's first day, one check-in on any of its days. N runs from 2 to 365. | Windows |
| Some days of the week | `on mon,wed,fri` | `Schedule::onWeekdays([1, 3, 5])` | Each of these days, one check-in; the other days ask nothing. | Days |
| N times a week | `3 per week` | `Schedule::perWeek(3)` | Each ISO week, Monday to Sunday, N check-ins. N runs from 1 to 7. | Weeks |
| N times a month | `10 per month` | `Schedule::perMonth(10)` | Each calendar month, N check-ins. N runs from 1 to 31. | Months |

A window of N days is lenient on purpose: "every 3 days" is met by a check-in on any of the three days, not on a fixed
one.

## Rules

`Schedule::parse()` reads a rule in any case, its words separated by spaces or tabs; the days of the week are written
`mon` to `sun`, separated by commas, spaces or both. `toString()` writes the rule back in its canonical form, which is
how the database stores keep it:

```php
Schedule::parse(' On FRI, mon ')->toString();   // "on mon,fri"
Schedule::parse('10 PER MONTH')->toString();    // "10 per month"
```

A rule is at most 64 bytes long. Counts are whole numbers without leading zeros or signs. Anything else is refused with
`InvalidSchedule`, whose message says what a rule looks like; the parser has a fuzz target that holds it to that.

## Periods

`periods($first, $through)` gives the periods from a habit's first day to a day, in order, the last one holding that
day. A period cut by the first day asks no more check-ins than it has days: a habit due 3 times a week started on a
Saturday asks 2 check-ins of its first week. Each period also carries the number of days it stands for in the
strength — its length, or seven divided by the number of due days for a habit due on some days of the week —: see
[Strength](strength.md).

A habit whose schedule changes keeps its check-ins; its progress is worked out again under the new schedule from its
first day.

## Days and time zones

A habit's days are lived in its time zone. `Day` is a calendar day without a time or a zone, kept as its number of days
from 1970-01-01, so that days compare as integers; `Day::of($instant, $zone)` gives the day an instant falls on there.
`Habits` asks the clock for the instant and the habit for the zone: for a member in Toronto, 03:30 UTC on November 2
is still November 1.
