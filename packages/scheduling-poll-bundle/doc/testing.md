# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --import ./tests/js/register.mjs --test 'tests/js/**/*.test.mjs'
```

The unit suite, under `tests/Unit`, runs without a kernel or a network, on the stores in memory and on SQLite, which
the same tests hold to one contract. It covers a closing by hand racing the deadline, in one process and on two
connections to one database, announcing the date once; time zones across the changes of the hour; a revoked link; who
sees the answers before the closing; and an iCalendar file checked line by line. In the monorepo, Infection holds the
package at 100 % of covered mutants.

The functional suite, under `tests/Functional`, boots a small application — FrameworkBundle, DoctrineBundle on SQLite,
TwigBundle, TwigComponentBundle with the recipes of the PHIL UX kit — and requests the pages: joining, answering without
JavaScript, a link that opens nothing, a closed poll and the organizer's table.

The tests of the Stimulus controllers, under `tests/js`, run with Node's test runner, Stimulus resolved to a stub.

## Testing code that uses the bundle

Set `store: memory` in the test environment: the polls, respondents and links live in memory and are emptied between
two requests. A `MockClock` as the `clock` service moves the time past a deadline; `closeDue()` then closes the polls,
and `eraseExpired()` erases what is past its delay, without a worker.
