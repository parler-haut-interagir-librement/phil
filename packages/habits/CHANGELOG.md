# Changelog

All notable changes to `phil/habits` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/dbal` 4.6).

## 0.1.0-alpha - 2026-11-20

### Added

- `Habit`, a habit of a member named by an opaque identifier, never an email address, with its schedule, time zone,
  first day, optional numeric target and last day once archived.
- `Schedule` and `Frequency`: every day, once in every N days, on some days of the week, N times a week or a month;
  `parse()` and `toString()` of their rules, and `periods()`, the `Period`s they cut from the habit's first day.
- `Day`, a calendar day kept as its number of days from 1970-01-01, read from `YYYY-MM-DD` or from an instant in a
  time zone.
- `CheckIn` and `CheckInStatus`: done, done in part towards the target, skipped or frozen, recorded in UTC.
- `ProgressCalculator`, `Progress`, `PeriodProgress`, `Streak` and `StreakUnit`: streaks, current and best, that
  excused days and the running period spare, and the strength from 0 to 1, shown as a whole percentage.
- `HalfLifeStrength`, an exponentially smoothed strength whose memory halves every fourteen days, behind the port
  `StrengthModel`.
- `Habits`: `start()`, `get()`, `today()`, `change()`, `archive()`, `checkIn()`, `freeze()`, `undo()`, `progress()`,
  `history()`, `ofMember()`, `remove()` and `forgetMember()`, with the days a check-in may go back.
- `Milestones`, and the events `HabitChecked`, `HabitUnchecked`, `StreakReached` and `StrengthReached`, naming the
  habit and its member.
- The ports `HabitStore` and `CheckInStore`; stores in memory, and `DbalHabitStore` and `DbalCheckInStore` on
  Doctrine DBAL with `configureSchema()`, tested on SQLite, MySQL 8.4 and PostgreSQL 17.
- `HabitsException`, implemented by `InvalidHabit`, `InvalidSchedule`, `InvalidCheckIn`, `InvalidDay` and
  `HabitNotFound`.
- Fuzz targets for the schedule rules and the days, and a benchmark of the progress over ten years.
- The package skeleton: `composer.json`, `phil-dev.php` and `composer-dependency-analyser.php`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
