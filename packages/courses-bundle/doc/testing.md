# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

| Suite | What it covers |
|---|---|
| `tests/Unit` | The domain, the entry points, the import and its blocks, the completions, the rules of cmi5, the `satisfied` statements, the SCORM run time, its attempts, tokens, endpoint, frame and privacy module, the review of an ended attempt, the time limits, the privacy module of cmi5, the stores, the upgrade of the tables, on SQLite in memory, and the remote learning record store on a simulated client |
| `tests/Functional` | The bundle in a small application: the pages, the administration page, the content origin, the fetch URL, the xAPI endpoint, the commands, and a SCORM 1.2 and a SCORM 2004 package imported, launched and committed to, suspended, resumed and attempted again, reviewed once ended, and a time limit held |
| `tests/Integration` | The tables, their upgrade and the queries on MySQL 8.4 and PostgreSQL 17 — the SCORM attempts and their keys compared byte by byte among them, their time and their review, the cmi5 records of a learner exported and erased —, and a statement sent to a real learning record store |

## The scripts

The scripts of the SCORM run time are tested with Node.js's own test runner, Stimulus replaced by a stub:

```sh
node --import ./tests/js/register.mjs --test 'tests/js/*.test.mjs'
```

`cmi.test.mjs` runs the calls of `tests/js/fixture/vectors.json` against the API in the browser's code, and
`BrowserParityTest` runs the same calls against the data model of phil/elearning-packages, with the descriptions of
`tests/js/fixture/model-*.json` held to what the server hands the browser: both answer each call alike. The fixture is
written from the server's answers. `tests/js/fixture/review.json` does the same for a review: the values a review
hands the content, which `ScormReviewTest` holds to what the server gives for an ended attempt, and the calls of the
content in review mode, which the server and the browser answer alike.

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
or refused. The fuzz target `scorm-commit` feeds bodies nobody wrote to the reading of a commit and to the data model of
each version: a body is read or refused, and what is kept stays within its bound. The fuzz target `catalogue-query`
feeds query strings nobody wrote to the reading of a catalogue page: whatever they hold, the query read stays within its
bounds, and the query string it writes back reads as the same query. The readers of the packages and the data model are
fuzzed in phil/elearning-packages.

```sh
composer fuzz -- cmi5-statement scorm-commit catalogue-query
```

## Benchmarks

`tests/Benchmark/CoursesBench.php` measures checking a statement, placing the activities of a course of a hundred
activities for a learner, the commit of a SCORM session whose attempt holds fifty interactions, and a page of a
catalogue of five hundred courses filtered by words and sorted by title, with where the learner stands. The readers of
the packages are measured in phil/elearning-packages.

## Testing code that uses the bundle

`CourseTables::configure()` creates the tables on any DBAL connection, SQLite in memory included. A `MockClock` as the
`clock` service fixes the moments the releases and sessions read.
