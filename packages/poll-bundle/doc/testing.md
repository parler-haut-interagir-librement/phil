# Testing

## The bundle's tests

The suite `tests/Unit` tests the domain — the bounds of a poll and of its options, the opening and the closing to the
second, closing and rescheduling, named and anonymous ballots —; `Polls` over the in-memory stores and a mock clock —
the policy asked for every action, ballots cast, changed and withdrawn, anonymous ballots changed by receipt, a
second ballot refused, the visibility of the results in each case, the events, the counts kept in a cache until the next
ballot —; the single transferable vote — the published food election of the English Wikipedia's article, the votes
and the exhausted value adding up in every round, transfers, ties, the bounds —; the views, the reading of a posted
form and the Twig functions; the Doctrine and in-memory stores against one shared contract, on SQLite in memory, the
in-memory ones emptied by a reset; the mapping of the three tables; and the bundle's configuration and services. The
suite `tests/Functional` boots a kernel with DoctrineBundle on SQLite and TwigBundle, runs an anonymous ranked poll
counted by a method of the application, heard by a listener, and draws the templates in English and in French.

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
