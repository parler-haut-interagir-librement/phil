# Security and privacy

## What the library keeps of a member

| Data | Kept by | Personal |
|---|---|---|
| A habit: its member, name, schedule, time zone, first day, target, last day | `HabitStore` | The member's identifier; the name they gave the habit, which may say much about them |
| A check-in: its habit, day, status, value and instant | `CheckInStore` | What the member did, day by day |

A member is named by a stable opaque identifier of the application — a user's UUID —, never by an email address:
`Habit` refuses an identifier with an `@`. The events carry the identifiers and the numbers reached, never the name of
a habit nor a value checked in, so that a queue or a log that keeps them holds nothing more.

Habits can be about health, faith or recovery. Treat the name of a habit and its check-ins as sensitive: show them to
their member only unless they choose otherwise.

## Access and erasure

`Habits::ofMember($member)` lists a member's habits, archived ones included, and `history()` their check-ins: their part
of an access request. `forgetMember($member)` forgets every habit of a member and its check-ins, and returns the number
of habits forgotten; `remove($id)` forgets one habit.

## Bounds on every input

| Input | Bound |
|---|---|
| A habit or member identifier | 1 to 255 visible ASCII characters |
| A habit's name | 1 to 200 characters, without surrounding spaces or control characters |
| A target | 2 to 1,000,000, or none |
| A check-in's value | 0 to 1,000,000,000 |
| A schedule rule | 64 bytes; counts of three digits at most, then the bounds of each kind |
| A day | `YYYY-MM-DD`, from 0001-01-01 to 9999-12-31 |
| Going back | 0 to 366 days, seven by default; never ahead of today |
| A freeze | 366 days long, ending at most 366 days ahead |
| Milestones | 50 streak lengths and 50 strengths |
| A table name | 63 lowercase letters, digits or `_` |

The work of a read is bounded by the habit's age: one pass over its periods. The parsers of rules and days are fuzzed.
SQL statements take every value as a parameter; table names are checked before they are written into a statement.
