# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services it gives, the meters built from it and the refusals of bad
ones, the rate limiters' counting on their meters and their decisions, retries counted once per `Idempotency-Key`
and malformed keys refused, and the purge command.
`tests/Functional` boots a small application — FrameworkBundle, DoctrineBundle with the ORM on SQLite, `phil/rate-limit-bundle` and this bundle —,
creates the counter tables from the schema the ORM generates, sends requests to rate-limited actions until they are
refused, and runs the purge.

From the monorepo root:

```sh
vendor/bin/phpunit packages/usage-meter-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection, like the library.

## Testing an application

In the test environment, keep the counters in memory and the time in hand:

```yaml
# config/packages/phil_usage_meter.yaml
when@test:
    phil_usage_meter:
        store: memory
        clock: test.clock
```

```yaml
when@test:
    services:
        test.clock:
            class: Symfony\Component\Clock\MockClock
            public: true
```

Each kernel boot starts with empty counters. A test of a rate-limited action sends requests until the 429, then moves
the clock past `Retry-After` and sends one more. A test of a quota calls `UsageMeter::consume()` from the container, or
asserts on `usage()` after the action.
