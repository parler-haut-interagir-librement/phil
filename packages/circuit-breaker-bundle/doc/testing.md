# Testing

## The bundle's tests

`tests/Unit` checks the configuration and the services it gives — the default store and its pool, the application's
store and clock, the decorated clients, the tagged overrides and their autoconfiguration, the parameter of the keys,
the collector — and the refusals of bad circuits; a compiled container where an autoconfigured override forces one
circuit open; the cache store and the entries of another shape it reads as closed; a client behind its circuit through
`MockHttpClient`, counting server and transport errors, refusing while open and closing again after a trial call; and
the profiler collector.

From the monorepo root:

```sh
vendor/bin/phpunit packages/circuit-breaker-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection, like the library.

## Testing an application

In the test environment, keep the states in a pool of the test and the time in hand:

```yaml
when@test:
    framework:
        cache:
            pools:
                cache.circuits:
                    adapter: cache.adapter.array

    phil_circuit_breaker:
        cache_pool: cache.circuits
        clock: test.clock

    services:
        test.clock:
            class: Symfony\Component\Clock\MockClock
            public: true
```

A test trips a circuit through `MockHttpClient` answers of `503`, checks that the next request throws `CircuitOpen`
without reaching the mock, then moves the clock past `open_seconds` and sends a trial request.
