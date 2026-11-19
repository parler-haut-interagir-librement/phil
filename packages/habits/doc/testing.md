# Testing

## The library's tests

The suite `tests/Unit` tests days across years, leap days and time zones; every rule of the schedules and the periods
they cut; habits and check-ins at their bounds; streaks and strength on daily, weekly and weekday habits with done,
partial, skipped and frozen days; `Habits` on a frozen clock, with the events it dispatches; and one contract run
against the stores in memory and on SQLite in memory. `tests/Integration` runs the same contract against MySQL and
PostgreSQL.

From the monorepo root:

```sh
vendor/bin/phpunit packages/habits/tests/Unit
```

A test that needs a real server reads its URL from an environment variable and is skipped without it:
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`. `phil-dev services` starts MySQL 8.4 and PostgreSQL 17 in Docker
and prints the variables:

```sh
vendor/bin/phil-dev services test
vendor/bin/phpunit packages/habits/tests/Integration
```

The fuzz targets `schedule-rule` and `day` hold the parsers to their contract; `tests/Benchmark` measures the progress
of a habit kept for ten years. In the monorepo, Infection holds the library at 100 % of covered mutants.

## Testing code that uses habits

Build `Habits` on the stores in memory and a frozen clock, such as Symfony's `MockClock`, and move the clock to cross a
day:

```php
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-11-02 12:00:00', 'UTC');
$habits = new Habits(new InMemoryHabitStore(), new InMemoryCheckInStore(), $clock);

$habits->start('habit-1', 'member-1', 'Read', Schedule::daily(), new DateTimeZone('UTC'));
$habits->checkIn('habit-1');
$clock->modify('+1 day');

self::assertSame(2, $habits->checkIn('habit-1')->streak->current);
```
