# Changelog

All notable changes to `phil/calendar-import` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

### Security

- The messages of `SourceUnavailable` from `IcsUrlSource`, which the state keeps as the failure's message, name the
  feed by its address without its query and fragment (`?…`): the secret of a private feed no longer reaches the state,
  a log or a screen of failures.

## 0.1.0-alpha - 2026-11-27

### Added

- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- `ICalendarReader`: the events of an iCalendar text (RFC 5545), read strictly and within `ReaderLimits` (bytes,
  events, bytes of an unfolded line, depth, components), a text past a bound refused with `LimitExceeded` naming it;
  lines unfolded, parameters decoded (RFC 6868), texts unescaped and kept as plain text; `UID`, `SEQUENCE`,
  `LAST-MODIFIED`, `DTSTAMP`, `DTSTART`, `DTEND`, `DURATION`, `SUMMARY`, `DESCRIPTION`, `LOCATION`, `URL` through
  `phil/url`, `STATUS`, `CATEGORIES`, `RRULE`, `RDATE` and `EXDATE` through `phil/recurrence`, `RECURRENCE-ID`; zones
  by IANA name, Windows name or a simple `VTIMEZONE`; an event that cannot be read refused alone with its
  `Rejection`; duplicates of a UID resolved to the newest; private and confidential events refused.
- `EventSource`, the port of a source, and `IcsUrlSource`, a feed read by its address through `phil/safe-http`
  (`webcal://` read as `https://`, no private network, redirects bounded, conditional reads by `ETag` and
  `Last-Modified`), with `CalendarSource`, `FetchValidators`, `Fetched`, `FailureKind` and `SourceUnavailable`.
- `SourceEvent` and `EventVersion` (`SEQUENCE`, `LAST-MODIFIED`, fingerprints of the content and of each field),
  `Snapshot` and `SnapshotDiff` (added, changed with their `ChangeReason`, removed), written and read back as arrays.
- `EditorOverride` and the `OverrideStore` port: fields replaced and events hidden, applied at every read, a change of
  a replaced field reported.
- `CalendarImporter`, `ImportOutcome`, `Change` and `ImportedDraft`: a refresh that keeps the last snapshot when the
  source fails, dates the failure, keeps the last version of an event refused this time, and gives drafts marked with
  their source, key and UID; `refreshDue()` with `RefreshPolicy` (interval, doubled retries, longest retry).
- The `SourceStateStore` port; nothing is kept between two requests.
- The fuzz target `ics-reader`, and the benchmark `ICalendarReaderBench`.
- The documentation in `doc/`, one file per topic, indexed by the README: reading iCalendar, sources, refreshing a
  source, extension points, testing and reference.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
