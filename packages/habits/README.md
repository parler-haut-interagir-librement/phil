# phil/habits

**Habits with schedules, check-ins, streaks that do not punish, and a smoothed strength from 0 to 100 %.**

phil/habits keeps the habits a member sets for themselves — read every day, run three times a week, call a friend on
Sundays — and what they did with them, day by day. A habit has a schedule: every day, once in every N days, on some
days of the week, or N times a week or a month. Its days are the member's own, in the habit's time zone; the instants
are recorded in UTC.

A day is checked in as done, done in part towards a numeric target — 5 pages of 20 —, skipped, or frozen ahead of
time for a trip or an illness. The library works out two numbers from the check-ins. The **streak** counts the
periods met in a row, with the best one kept; a skipped or frozen day, and the period still running, neither add to it
nor break it. The **strength**, shown beside the streak, is an exponentially smoothed average of how much of each
period was done, whose memory halves every fourteen days: a missed day lowers it by a share, never back to zero, and a
day done in part counts for its part.

The library dispatches events after a check-in — the habit checked, a streak length reached, a strength reached —, so
that a gamification layer listening to them can award points or badges; it never awards anything itself. Members are
named by a stable opaque identifier of the application, never by an email address.

It is a plain PHP library on the PSR clock and event dispatcher. Habits and check-ins are kept behind two ports, with
stores in memory and stores on Doctrine DBAL, tested on SQLite, MySQL 8.4 and PostgreSQL 17. Its services keep no
state between calls and are safe in a long-running worker.

## Status

| Part | State |
|---|---|
| Schedules: daily, every N days, days of the week, N per week, N per month, and their rules | Implemented, tested and fuzzed |
| Check-ins: done, in part towards a target, skipped, frozen; going back, taking back | Implemented and tested |
| Streaks, current and best, that excused days and the running period spare | Implemented and tested |
| The strength from 0 to 100 %, and the port to replace its formula | Implemented, tested and benchmarked |
| Events for a check-in, a streak and a strength reached | Implemented and tested |
| Stores in memory, and on Doctrine DBAL for SQLite, MySQL and PostgreSQL | Implemented and tested |
| A member's habits listed and erased | Implemented and tested |
| Points, badges or rewards for habits | Not part of the library: a gamification package listens to its events |
| Configuration, forms and pages | Not part of the library: the application, or a bundle on it, provides them |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the services to build. |
| [Schedules](doc/schedules.md) | The kinds of schedule, their rules, and the periods they cut. |
| [Check-ins and streaks](doc/check-ins-and-streaks.md) | Checking in, going back, freezing, taking back, and how a streak counts. |
| [Strength](doc/strength.md) | The formula of the strength, its half-life, and how to read it. |
| [Storage](doc/storage.md) | The ports, the stores in memory and on Doctrine DBAL, and their tables. |
| [Events](doc/events.md) | The events dispatched, for a gamification layer to listen to. |
| [Security and privacy](doc/security.md) | What is kept of a member, the erasure, the bounds on every input. |
| [Extension points](doc/extension-points.md) | The stores, the strength formula, the milestones, and when they are stable. |
| [Testing](doc/testing.md) | The package's tests, and how to test code that uses habits. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require phil/habits
```

```php
use Phil\Habits\CheckInStatus;
use Phil\Habits\Schedule;

$habits->start('habit-7', $memberId, 'Read', Schedule::parse('3 per week'), new DateTimeZone('America/Toronto'));

$progress = $habits->checkIn('habit-7');

$progress->streak->current;      // 1 — weeks met in a row
$progress->streak->unit;         // StreakUnit::Week
$progress->strengthPercent();    // rises with each week met, falls a little with each week missed
$progress->current?->done;       // 1 check-in of the 3 this week asks

$habits->checkIn('habit-7', CheckInStatus::Skipped, day: $yesterday);
```

## Requirements

PHP 8.5 or later with `mbstring`. `doctrine/dbal` 4.5 for the database stores.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
