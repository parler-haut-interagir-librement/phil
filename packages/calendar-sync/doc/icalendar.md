# The iCalendar reader

`ICalendar\ICalendarReader::busy()` gives the busy periods of an iCalendar text (RFC 5545) that overlap a window. The
adapters `ics` and `caldav` read with it; an application may read a calendar it already holds.

## What is read

| Component | What gives a period |
|---|---|
| `VEVENT` | Its start, and its end, its duration, or a day for a whole day; a time without either gives none |
| `VEVENT` with `RRULE` | Each occurrence of the rule that overlaps the window, read by `phil/recurrence` |
| `RDATE`, `EXDATE` | Occurrences added and taken away, of the event's length; a period of `RDATE` by its start |
| `RECURRENCE-ID` | An occurrence changed: read on its own, and taken away from its rule |
| `VFREEBUSY` | Each `FREEBUSY` period, a start and an end or a duration, but the `FREE` ones |

An event that is cancelled (`STATUS:CANCELLED`) or transparent (`TRANSP:TRANSPARENT`) gives nothing. A tentative event
(`STATUS:TENTATIVE`) and a `BUSY-TENTATIVE` period give tentative periods.

Only these properties are read: `DTSTART`, `DTEND`, `DURATION`, `RRULE`, `RDATE`, `EXDATE`, `RECURRENCE-ID`, `UID`,
`STATUS`, `TRANSP`, `FREEBUSY`. The others — the title, the description, the place, the guests, the attachments — are
skipped without being decoded, and the properties of a component inside an event, an alarm, are not read.

## Time zones

- A time with `Z` is in UTC.
- A time with `TZID` is in that zone: an IANA name, a leading `/` dropped, or a Windows name as Outlook writes them,
  read by ICU. A name neither knows is read in the calendar's zone. `VTIMEZONE` definitions are not read.
- A floating time, and a whole day, is in the calendar's zone: the connection's.

The occurrences of a rule keep their first one's time on the wall clock of its zone, across changes of time. The end
of an occurrence is as many days and seconds after its start as the event's end is after its own.

## Bounds

| Bound | Value |
|---|---|
| The text | 10 MiB |
| Components | 20,000, nested 8 deep |
| A property read | 20,000 bytes once unfolded |
| Repeated events | 1,000, counted before any is expanded, and across the `calendar-data` of one CalDAV answer |
| Dates added or removed, for an event | 10,000 |
| Periods within the window | 10,000 |

## What is refused

A text past a bound is refused with `InvalidCalendarData`, as is a text that does not start with `BEGIN:VCALENDAR`,
holds something after its last `END:VCALENDAR`, ends components out of order or leaves one open, has a line without a
colon or a malformed parameter, an event without a start, a date that is not written `20261110` or `20261110T190000`
or that does not exist, a negative or empty duration, or a rule `phil/recurrence` does not read — an hourly rule, a
`BYWEEKNO`. A calendar is refused whole rather than half read: half read, it would leave times free that are not.

The reader is a fuzz target: see [testing](testing.md).
