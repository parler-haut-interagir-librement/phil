# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The domain, the entry points, the import and its blocks, the completions, the rules of cmi5, the `satisfied` statements, the stores, the upgrade of the tables, on SQLite in memory, and the remote learning record store on a simulated client |
| `tests/Functional` | The bundle in a small application: the pages, the administration page, the content origin, the fetch URL, the xAPI endpoint and the commands |
| `tests/Integration` | The tables, their upgrade and the queries on MySQL 8.4 and PostgreSQL 17, and a statement sent to a real learning record store |

The packages the tests import — a SCORM 1.2 manifest, a SCORM 2004 manifest with sequencing, a cmi5 course structure —
are written for the tests, in `tests/Unit/Fixture/Packages.php`, and zipped in the temporary directory. In the
monorepo, Infection holds the package at 100 % of covered mutants.

The integration tests read the URL of their server from the environment and are skipped without it:
`PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL`, such as
`mysql://phil:phil@127.0.0.1:13306/phil_test?serverVersion=8.4`.

The test of the remote learning record store reads its endpoint from `PHIL_TEST_LRS_URL` — an `https` address on a
public host, such as a test tenant of a hosted store —, the key and secret of HTTP Basic authentication from
`PHIL_TEST_LRS_USERNAME` and `PHIL_TEST_LRS_PASSWORD`, and the xAPI version from `PHIL_TEST_LRS_VERSION`, `1.0.3` by
default. It sends one statement twice, and is skipped without the endpoint:

```sh
PHIL_TEST_LRS_URL=https://lrs.example.org/xapi/ PHIL_TEST_LRS_USERNAME=key PHIL_TEST_LRS_PASSWORD=secret \
    vendor/bin/phpunit --no-configuration tests/Integration/RemoteLrsTest.php
```

The functional tests stand in for Symfony Security with an authorization checker that grants the roles of the
`X-Roles` header, and wrap the store inside the bundle in one they make refuse the statements, as a remote store out
of reach would.

## Fuzzing

The fuzz target `cmi5-statement` feeds statements nobody wrote to the checks of the xAPI endpoint, from the seeds of
`tests/Fuzz/corpus/cmi5-statement`: a statement is accepted only for the actor, registration and session of its launch,
or refused. The readers of the packages are fuzzed in phil/elearning-packages.

```sh
composer fuzz -- cmi5-statement
```

## Benchmarks

`tests/Benchmark/CoursesBench.php` measures checking a statement and placing the activities of a course of a hundred
activities for a learner. The readers of the packages are measured in phil/elearning-packages.

## Testing code that uses the bundle

`CourseTables::configure()` creates the tables on any DBAL connection, SQLite in memory included. A `MockClock` as the
`clock` service fixes the moments the releases and sessions read.
