# Reference

| Type | Kind | Role |
|---|---|---|
| `RecurrenceRule` | `final readonly class` | An RFC 5545 rule: reading, checking, writing |
| `Recurrence` | `final readonly class` | A rule from its first occurrence: occurrences, next one, exceptions, added dates |
| `RecurrenceDates` | `final readonly class` | A line of `RDATE` or `EXDATE` dates: reading, checking, writing |
| `Frequency` | string-backed enum | `YEARLY`, `MONTHLY`, `WEEKLY`, `DAILY` |
| `Weekday` | string-backed enum | `MO` to `SU`, and their ISO numbers |
| `WeekdayNum` | `final readonly class` | A weekday of `BYDAY`, with its place |
| `Exception\RecurrenceException` | interface | Every exception of the library |
| `Exception\InvalidRule` | `final class`, `InvalidArgumentException` | A rule or a line of dates that cannot be, or is not read |

## schema.org

`SchemaOrg::of(Recurrence)` gives a `Schedule`: start date and time, `repeatFrequency` as an ISO 8601 duration (`P2W`),
`byDay` (`https://schema.org/Tuesday`, or `2TU` for an ordinal), `byMonthDay`, `byMonth`, `repeatCount`, `endDate` (an
end time written as the day it falls on in the first occurrence's time zone), `exceptDate`, `scheduleTimezone`. A
`Schedule` has no property for added dates: they are left out. The array has a `@type` and no `@context`: it is meant to
be embedded in a larger JSON-LD document.

| Type | Kind | Role |
|---|---|---|
| `SchemaOrg` | `final class` | `of(Recurrence)`: a schema.org `Schedule`, for the `eventSchedule` of an `Event` |
