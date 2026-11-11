# Asking

| Method | Gives |
|---|---|
| `isOpenAt($moment)` | Whether the place is open, ranges from the day before that close past midnight included |
| `nextOpening($moment)` | The next time the place opens after the moment, in its time zone; `null` when it does not within a year |
| `hoursOn($day)` | A calendar day's `DayHours`: its special hours, or its weekday's |
| `specialOn($day)` | The `SpecialHours` of a day, or `null` |
| `on(DayOfWeek)` | A weekday's hours |
| `groups()` | The week from Monday in runs of consecutive days with the same hours, for a table |
| `isAlwaysClosed()` | Whether no day of the week opens |

A moment is turned to the place's time zone before it is read: asking at `14:30 UTC` about a shop in Toronto asks
about `09:30` there. While the place is open, `nextOpening()` gives its next opening, not the current one.

`nextOpening()` reads each special period once, for the days of the year ahead it covers, and then walks the days
without formatting a date for each one; a place the week keeps closed is looked at on its special days only, so an
always-closed place with no special hours answers `null` at once.
