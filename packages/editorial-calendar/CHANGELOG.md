# Changelog

All notable changes to `phil/editorial-calendar` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `PlannedItem`: a planned publication, its content by `ResourceReference`, its language, its author, its section, its
  state and its moment kept in UTC, with `plan()`, `restore()`, `transitionTo()`, `reschedule()`, `edit()` and
  `withoutSection()`.
- `ItemState` and its workflow: idea, drafting, ready, scheduled, published, withdrawn; a scheduled item always has a
  moment.
- `AuthorId`: an author named by a stable identifier, never an email address.
- `PublicationBatch`: items published together, at a moment in UTC or by hand.
- `EditorialCalendar`: the items and batches by identifier, immutable; `applyBatch()` publishes every item of a batch
  or none, `BatchFailed` naming the item that refused; an item of a batch is never published alone; `dueBatches()`,
  `manualBatches()`, `unscheduled()` and `moment()`.
- `CalendarView`, `Period`, `Day`, `Entry` and `EntryKind`: day, week and month views in a time zone, correct across
  changes of time, weeks from any first day, and lists between two moments; each batch is one entry with its items.
- `Filter`: the items of some authors, sections or states, for every view and for `unscheduled()`; a filter that is not
  empty leaves out the transitions planned elsewhere.
- `TransitionReader`, `PlannedTransition` and `NoTransitions`: the port through which the views read transitions
  planned elsewhere.
- `Review`, `ReviewRules`, `Issue` and `IssueKind`: repeated content in one language, items planned while still ideas
  or drafts, publications closer than a spacing, and gaps longer than a maximum.
- `ICalendarWriter`: the publications as an iCalendar stream (RFC 5545), one event per item alone and per batch,
  escaped and folded at 75 octets.
- `EditorialCalendarException`, `InvalidItem`, `InvalidTransition`, `UnknownItem` and `BatchFailed`.
- The documentation in `doc/`, one file per topic, indexed by the README, with a template drawing a view with Twig and
  the recipes of the PHIL UX kit.
- The benchmarks of a month, its review and a year's export over 5,000 items.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
