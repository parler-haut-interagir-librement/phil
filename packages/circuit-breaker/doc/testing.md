# Testing

## The library's tests

The suite `tests/Unit` tests the keys and their bounds; the policies, their bounds and the exact threshold in integer
arithmetic; every transition of the state machine to the second — the window that restarts, the open duration, the
trial successes, the failure that opens a half-open circuit again, the outcome that races an open circuit —; the
breaker over an in-memory store and a frozen clock, a state shared by two breakers of one key, a forced circuit that
records nothing; the registry; and the overrides asked together.

From the monorepo root:

```sh
vendor/bin/phpunit packages/circuit-breaker/tests
```

The library is held to 100 % covered mutation score by Infection.

## The benchmark

`tests/Benchmark/CircuitBreakerBench.php` measures what every guarded call costs: the ask, the ask and report of a
success, one transition of a snapshot, and the parsing of a key. `composer bench`, from the monorepo root, runs it
with the other benchmarks.

## Testing code behind a breaker

Build the breaker on an in-memory store and a frozen clock, such as Symfony's `MockClock`; trip it by recording
failures, and move the clock to let it recover:

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Symfony\Component\Clock\MockClock;

$clock = new MockClock('2026-03-14 10:30:00');
$breaker = new CircuitBreaker(CircuitKey::of('http.geocoding'), CircuitPolicy::of(50, 2, 60, 30), $memory, new CircuitOverrides(), $clock);

$breaker->recordFailure();
$breaker->recordFailure();
self::assertNull($geocoder->locate('1 Main St'));   // the fallback, and no call made

$clock->sleep(30);
```

An in-memory store is a dozen lines: an array of snapshots by key value, `load()` reading it and `save()` writing it.
