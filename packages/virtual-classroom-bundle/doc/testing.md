# Testing

The tests are PHPUnit tests, run from the root of the monorepo with `composer test`, or on their own:

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

- `tests/Unit`: the room, `Classrooms` against two fake servers and a store in memory, the proof of the webhook, the
  controllers, the commands, the adapters of Security, Routing and phil/privacy-bundle, the activity type, the wiring
  of every option; the stores against `StoreTestCase`, in memory and on SQLite.
- `tests/Functional`: an application with FrameworkBundle, DoctrineBundle on SQLite, SecurityBundle, TwigBundle,
  phil/courses-bundle and phil/privacy-bundle, a BigBlueButton server behind a scripted HTTP client and a Jitsi server:
  joining, the webhook of a room and of an account, a live class completed by its attendance.
- `tests/Integration`: the DBAL store on MySQL 8.4 and PostgreSQL 17, read from `PHIL_TEST_MYSQL_URL` and
  `PHIL_TEST_POSTGRESQL_URL`; skipped without them. `composer test:services` starts the servers.

## Benchmark

`tests/Benchmark/AttendanceBench.php`, run by `composer bench`: a notification of 600 events applied to the stays, and
the report of the attendance of 300 participants, from the store in memory.
