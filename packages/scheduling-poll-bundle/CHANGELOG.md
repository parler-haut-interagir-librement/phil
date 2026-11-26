# Changelog

All notable changes to `phil/scheduling-poll-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-25

### Changed

- The development requirements accept `phil/ux-kit` 0.6, the minor version of its main branch and no earlier one, so
  that the package installs on its own beside that package.

### Fixed

- The compiler pass that removes Messenger handlers runs before the one of
  `symfony/messenger`, which Symfony 8.2.0-RC1 registers in its own bundle at the default priority: the container no
  longer fails to build with a handler locator that names a removed service.

## 0.1.0-alpha - 2026-11-24

### Added

- `SchedulingPolls`: polls of two to a hundred dates, kept in UTC with a time zone to show them, a deadline, a
  description and a place; respondents by name alone, an address, an account and a language optional, invited by the
  organizer or joining from the poll's page, up to `max_respondents`.
- Personal links of `phil/access-links` to the campaign `scheduling-poll:<poll>`: a token kept as its keyed hash,
  opened without side effect, issued again, revoked, opening for `link_lifetime` after the deadline.
- Answers yes, if need be or no, a date left out answered no, written in one conditional `UPDATE` while the respondent
  is not revoked and the poll is open; each respondent's time zone.
- `AvailabilityCount`: the answers counted by the count of choices of `phil/poll`, ordered by the most yes, then the
  most if need be, then the earliest date, ties sharing their place.
- The closing by the organizer on a date, or by the schedule at the deadline on the best date, without a date when
  nobody can come; one conditional write, the closing that wins queuing `AnnounceChosenDate` and dispatching
  `DateChosen` or `ClosedWithoutDate`.
- The mail of the date chosen, through Messenger messages of identifiers only: one `SendChosenDate` per respondent who
  answered, each notice claimed once and given back when the mail fails, in the respondent's language and time zone,
  with `event.ics` written by `phil/calendar-sync`; `RespondentAddresses` for the respondents with an account.
- `SchedulingPollViews`: the respondent's sheet and the organizer's table; the organizer sees the answers before the
  closing when the poll says so, the respondents as its `ResultsVisibility` of `phil/poll-bundle` says.
- The schedule `phil_scheduling_poll`, stateful in a cache pool and locked when a lock factory is there: the polls past
  their deadline closed, the names, addresses and time zones erased `link_lifetime` after the closing.
- `SchedulingPollPrivacy`: the export and the erasure of a person, by account or by address, through the contracts of
  `phil/privacy-bundle`.
- The Doctrine mapping of `phil_scheduling_poll`, `phil_scheduling_poll_slot` and `phil_scheduling_poll_respondent`,
  the stores on Doctrine and in memory, and the tables of the links added to the generated schema.
- The pages of a respondent, `phil_scheduling_poll_join` and `phil_scheduling_poll_answer`, with `no-referrer`,
  `private, no-store` and `noindex`, and an optional CSRF token; the templates `answer`, `join` and `results`, a table
  of radio buttons usable with the keyboard and without JavaScript, drawn with the recipes of the PHIL UX kit, in
  English and in French.
- The Stimulus controllers `timezone`, the dates in the zone the respondent picks, and `chosen`, the DOM event
  `scheduling-poll:chosen` that plays the kit's celebration.
- The configuration `phil_scheduling_poll`: store, links, lifetime, respondents, schedule, mailer, privacy and kit.
- The documentation in `doc/`, one file per topic, indexed by the README.
- The Flex recipe, in `recipe/0.1`.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
