# Changelog

All notable changes to `phil/calendar-sync` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-12

### Added

- `CalendarSync`: the busy periods of a connection read through its adapter, cut to a window, sorted, joined and
  given to the application's store, which is left alone when the read fails; events written into a connection and
  removed from it.
- The values: `BusyPeriod`, `BusyPeriods` and its 10,000 periods at most, `SyncWindow` of 400 days at most,
  `CalendarConnection`, `Credentials` that are never serialised nor dumped, and `OutboundEvent`.
- The ports `Contract\BusySource`, `Contract\CalendarDestination`, `Contract\ConnectionRepository`,
  `Contract\CredentialStore`, `Contract\BusyStore` and `Contract\OutboundEvents`.
- The iCalendar reader: busy periods only, from events — rules by `phil/recurrence`, added, removed and changed
  occurrences, cancelled and transparent events left out, tentative ones said so — and from free/busy times; zones by
  IANA or Windows name; bounded in bytes, components, depth, line length, repeated events (1,000, shared by the
  texts of one answer), dates and periods; a fuzz target.
- The iCalendar writer, for the events stored on a CalDAV server.
- The adapter `ics`: a published feed, read by `phil/safe-http`.
- The adapter `caldav`: the `free-busy-query` report, a `calendar-query` for the times of the events when the server
  does not offer it, events written under a hashed name; no private network, no redirect, no document type.
- The adapter `google`: `freeBusy`, events replaced or inserted under an identifier derived from theirs.
- The adapter `microsoft`: the calendar view in UTC, 20 pages at most, events found again by an extended property.
- The messages `RefreshBusyTimes` and `SyncEvent`, identifiers only, and their handlers.
- No proxy for any adapter, not even one of the environment's.
- Failures that name the adapter and the status, never an address, a token or a body.
- The package's files: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
