# Reading iCalendar

`ICalendarReader::read($text, $zone)` reads the events of an iCalendar text (RFC 5545) and gives a `ReadResult`: the
events read (`SourceEvent`), the events refused with their reason (`RejectedEvent`), and the calendar's name
(`X-WR-CALNAME`), if any. The reader keeps nothing between two reads: one instance serves every request of a worker.

```php
use Phil\CalendarImport\ICalendar\ICalendarReader;

$result = (new ICalendarReader())->read($text, new DateTimeZone('America/Toronto'));

foreach ($result->events as $event) {
    // $event->uid, $event->start, $event->title, $event->rule…
}

foreach ($result->rejected as $rejected) {
    // $rejected->uid, $rejected->reason (a Rejection), $rejected->message
}
```

The reader is strict: it follows RFC 5545 where it is clear, and refuses what it does not read rather than guessing.
A text is refused whole, or one event is refused alone; nothing is read half-way.

## The lines

- A line break — CRLF, a lone LF or a lone CR — followed by a space or a tab joins the next line to it (unfolding). A
  byte order mark at the start is skipped. The text is checked as UTF-8 once unfolded, so that a character folded in
  the middle of its bytes is read whole.
- Each line is a name, parameters and a value: `DTSTART;TZID=America/Toronto:20261110T190000`. A parameter value is
  quoted or bare; its quoted form holds no quote and no control character. Parameter values are decoded as RFC 6868
  writes them (`^n`, `^'`, `^^`).
- A value of type TEXT is unescaped: `\n` and `\N` are line breaks, `\\`, `\;` and `\,` the characters they escape;
  another backslash stays as written. The result is trimmed, and an empty text is no text.
- Every pattern is possessive: the time spent on a line grows with its length, never faster.

## The components

A text is one or more `VCALENDAR`, and nothing outside them. `BEGIN` and `END` must nest. Inside a calendar, the
reader reads each `VEVENT` and each `VTIMEZONE`; any other component (`VTODO`, `VJOURNAL`, `VALARM`, `VFREEBUSY`, an
`X-` component) is skipped, though it counts against the bounds.

`X-WR-TIMEZONE`, when it names an IANA zone, is the zone of the times written without one. Otherwise, the zone passed
to `read()` is; UTC by default.

## The properties of an event

| Property | Read as |
|---|---|
| `UID` | required; 255 bytes at most, on one line |
| `SEQUENCE` | a whole number, nine digits at most; 0 when absent |
| `LAST-MODIFIED`, `DTSTAMP` | a time in UTC, `20261110T190000Z` |
| `DTSTART` | required: a day (`20261110`, with `VALUE=DATE` or alone), a time in UTC (`…Z`), a time in its `TZID`, or a floating time |
| `DTEND` or `DURATION` | one of them at most; the end is a day when the start is one, and never before the start |
| `SUMMARY`, `DESCRIPTION`, `LOCATION` | plain text |
| `URL` | read by `phil/url`; an address it refuses is left out, and the event kept |
| `STATUS` | `TENTATIVE`, `CONFIRMED` or `CANCELLED`; `CONFIRMED` when absent |
| `CATEGORIES` | the tags, every line read, each tag kept once; 100 at most |
| `RRULE` | read by `RecurrenceRule::parse()` of `phil/recurrence`, in the zone of the start |
| `RDATE`, `EXDATE` | read by `RecurrenceDates::parse()` of `phil/recurrence`, in the zone of their own `TZID` |
| `RECURRENCE-ID` | the occurrence a changed instance replaces |
| `CLASS` | `PRIVATE` and `CONFIDENTIAL` refuse the event |

Without `DTEND` nor `DURATION`, an event of a day lasts that day, and an event at a time lasts no time. A duration is
written `P1W`, `P1D`, `PT2H30M` or `P1DT2H`, never negative, and an event of whole days lasts whole days.

An event that is cancelled is read, with `STATUS:CANCELLED`: the importer reports it, and an application removes or
marks the occurrence. Its draft says so with `isCancelled()`.

Other properties — `ATTENDEE`, `ORGANIZER`, `ATTACH`, `GEO`, alarms — are not read. An attendee's address never
reaches the library's output.

### The key of an event

Each event has a key: its UID, followed, for a changed occurrence, by `#` and the occurrence it replaces —
`20261110` for a whole day, `20261110T190000Z` in UTC otherwise. Snapshots, differences and overrides go by key.

### Duplicates

Two events of the same key are one too many. The one with the higher `SEQUENCE` is kept, then the later
`LAST-MODIFIED`, then the later `DTSTAMP`, then the first; the other is reported as `Rejection::DuplicateUid`.

## Time zones

A `TZID` is resolved in this order:

1. an IANA name PHP knows, in any case, with or without the leading `/` of a globally unique name;
2. a Windows name, as Outlook writes them (`Eastern Standard Time`), through ICU;
3. the `VTIMEZONE` of that `TZID` in the text, when it is simple: its `X-LIC-LOCATION` is an IANA name, or the `TZID`
   ends with one (`/mozilla.org/20050126_1/America/Toronto`), or every observance it holds has the same offset, read as
   a fixed offset.

The rules of a `VTIMEZONE` are never followed to make up a zone. An event in a zone none of these resolves is refused
alone with `Rejection::UnknownTimeZone`; the other events are read.

## What is refused

### The whole text

`UnreadableCalendar` is thrown, and no event is read:

- `InvalidCalendar` — the text is not UTF-8, holds no `VCALENDAR`, holds something outside one, has components that
  do not nest, or a line directly in a `VCALENDAR` that is not a name, parameters and a value (such a line in an event
  refuses that event; in a `VTIMEZONE`, it leaves that definition out);
- `LimitExceeded` — the text passes one of its `ReaderLimits`; `$exception->limit` names the bound (a `Limit`).

### One event

The event is refused with a `Rejection`, and the others are read:

| Rejection | When |
|---|---|
| `Malformed` | a line of the event cannot be read, a property held once appears twice, or both `DTEND` and `DURATION` |
| `MissingUid` | no `UID`, or an empty one |
| `MissingStart` | no `DTSTART` |
| `InvalidDate` | a date written otherwise, a day that does not exist, a year 0, an end before the start, a day and a time mixed |
| `UnknownTimeZone` | a `TZID` nothing resolves |
| `InvalidDuration` | a duration written otherwise, or hours in an event of whole days |
| `InvalidRecurrence` | a rule or dates `phil/recurrence` refuses, or too many dates |
| `InvalidValue` | a status, a sequence, a UID or categories outside what they allow |
| `Private` | `CLASS:PRIVATE` or `CLASS:CONFIDENTIAL` |
| `DuplicateUid` | another event of the same key was kept |
| `Unsupported` | `RECURRENCE-ID;RANGE=THISANDFUTURE` |

## Bounds

`ReaderLimits` bounds the work done on a text by numbers, never by the text:

| Bound | Default | Highest allowed |
|---|---|---|
| `bytes`, of the whole text | 5 MiB | 64 MiB |
| `events` | 5,000 | 100,000 |
| `lineBytes`, of an unfolded line | 16 KiB | 1 MiB |
| `depth`, `VCALENDAR` counted | 6 | 16 |
| `components`, of every kind | 20,000 | 200,000 |

A bound outside its range throws `InvalidLimits`. Each bound is checked as the text is read, and the reading stops at
the first one passed.

## Text, never HTML

Every text the reader gives is plain text, as the calendar wrote it once unescaped: nothing is read as HTML, and
nothing is decoded further. The application escapes it where it shows it — Twig does so by default.
