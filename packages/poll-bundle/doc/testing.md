# Testing

## The bundle's tests

The suite `tests/Unit` tests the domain — the bounds of a poll and of its options, the opening and the closing to the
second, closing and rescheduling, named and anonymous ballots —; `Polls` over the in-memory stores and a mock clock —
the policy asked for every action, ballots cast, changed and withdrawn, anonymous ballots changed by receipt, a second
ballot refused, the visibility of the results in each case, the events, the counts kept in a cache until the next change
—; the counts kept in a real pool over the Doctrine stores on SQLite — a new revision after each ballot cast, changed or
withdrawn, each closing and each new schedule, the counts of a deleted poll removed, the lifetime kept, and no voter,
receipt or hash of a receipt in what is kept of an anonymous poll —; the single transferable vote — the published food
election of the English Wikipedia's article, the votes and the exhausted value adding up in every round, transfers,
ties, the bounds —; the views, the reading of a posted form and the Twig functions; the Doctrine and in-memory stores
against one shared contract, on SQLite in memory, the in-memory ones emptied by a reset; the mapping of the three
tables; and the bundle's configuration and services. The suite `tests/Functional` boots a kernel with DoctrineBundle on
SQLite, TwigBundle and TwigComponentBundle with the recipes of the PHIL UX kit copied where the Toolkit installs them,
runs an anonymous ranked poll counted by a method of the application, heard by a listener, and draws the templates in
English and in French, their blocks replaced by an embedding template; then draws them again with the recipes of the
Shadcn kit of the Symfony UX Toolkit.

The suite `tests/Integration` runs a ballot and a closing on two connections to one database: the voter's request reads
the poll open, the manager's request closes it and commits, then the voter's request writes its ballot. A ballot cast,
anonymous or not, changed, changed by receipt and withdrawn is each refused with `VoteRefused`, and the ballots kept are
those before the closing. It runs on SQLite, on a database file in the temporary directory, and on MySQL 8.4 and
PostgreSQL 17 when `PHIL_TEST_MYSQL_URL` and `PHIL_TEST_POSTGRESQL_URL` name a server; without them, those tests are
skipped.

The suite `tests/Browser` opens the pages of that application in Chrome, through Panther and the chromedriver of the
system, with each kit: every kind of ballot filled and sent with the keyboard alone, a blank ballot, a ballot refused
and its alert, the form disabled for someone signed out and once the poll is closed, and the names and roles Chrome
computes for the groups, the controls and the bars. It runs apart, with the other browser suites:

```sh
composer test:browser
```

The Stimulus controller is tested with Node's test runner:

```sh
node --import ./packages/poll-bundle/tests/js/register.mjs --test packages/poll-bundle/tests/js
```

The count by Meek's method is a fuzz target, `meek-count`, run by `composer fuzz`; `tests/Benchmark/ElectionBench.php`
measures it on ten thousand ballots, and the results read from the cache.

From the monorepo root:

```sh
vendor/bin/phpunit packages/poll-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing code that uses polls

Configure the `memory` store in the test environment and freeze the clock:

```yaml
# config/packages/test/phil_poll.yaml
phil_poll:
    store: memory
```

```php
$clock->modify('2026-11-08 18:00:00 UTC');
$polls->cast($poll->id, ResourceReference::of('user', '8'), ['sun']);

self::assertSame(['sun'], $polls->results($poll->id)->winners());
```

Or build `Polls` directly on `InMemoryPollRepository`, `InMemoryVoteRepository`, Symfony's `MockClock`, a policy and
`CountingMethods::defaults()`.
