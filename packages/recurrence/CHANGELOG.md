# Changelog

All notable changes to `phil/recurrence` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `Frequency`, `Weekday`, `WeekdayNum`, `RecurrenceRule`; `RecurrenceException` and `InvalidRule`.
  `RecurrenceRule::of()` refuses what `parse()` cannot read back — an interval or a count above `MAX_NUMBER`, 9999,
  an end outside the years 1 to 9999 —, so that every rule written as text is read again.
- `Recurrence`, which lays out a period with its days as numbers, builds a date only for a day it keeps, and reads
  its exceptions once. `between()` and `next()` reach the period of their window by arithmetic when the rule has no
  `COUNT`, and `between()` stops at the period of its last moment. `MAX_PERIODS` counts only periods in a row that
  match no day, so an endless rule runs as long as it matches. The occurrences stop at the end of the year 9999, a
  rule ending on its last day (`UNTIL=99991231`) included. `between()` and `next()` walk no more than `MAX_WALKED`
  periods a call, so that a rule matching one day in years costs a bounded time. The dates `RDATE` adds, given to
  `of()`, are merged into the occurrences in order, once each, counted in neither `COUNT` nor `UNTIL`, and taken away
  by an exception like any occurrence; the exceptions are looked up by instant in a set.
- `RecurrenceDates`: an `RDATE` or `EXDATE` line read — whole days, UTC times, times with `TZID`, floating times read
  in a zone given, folded or not — and written back; periods are refused, and a line holds at most `MAX_DATES` dates
  in `MAX_LENGTH` bytes. Its fuzz target, `recurrence-dates`, checks that a line written back is read again the same.
- `SchemaOrg`: the schema.org form of the library's value, to embed in JSON-LD; it writes the `endDate` of an end
  time as the day it falls on in the first occurrence's time zone.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
