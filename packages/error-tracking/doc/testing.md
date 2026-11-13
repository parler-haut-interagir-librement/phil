# Testing

## The library's tests

The suite `tests/Unit` tests the frames and their names of closures and anonymous classes; the bounds of every text;
the request summary and its anonymized address; the events, their plain values and what reading them back refuses;
the factory on real throwables, chains and traces, inside and outside the project; the normalizer on each kind of
value; the fingerprint, what keeps it and what changes it; the scrubber on headers, tags, addresses and texts; the
sampler on a fixed engine; every transition of a group; and the tracker over an in-memory store, a counting throttle
and a recording alerter.

From the monorepo root:

```sh
vendor/bin/phpunit packages/error-tracking/tests
```

The library is held to 100 % covered mutation score by Infection.

## The benchmarks

`tests/Benchmark/FingerprintBench.php` measures the fingerprint of a chain of two exceptions of forty frames and the
normalizer on one message; `tests/Benchmark/ScrubberBench.php` measures the scrubber on an event with twenty headers,
ten tags and two messages, and on one text. `composer bench`, from the monorepo root, runs them with the other
benchmarks.

## Testing code that captures errors

Build the tracker on an in-memory store — an array of groups by fingerprint and a list of events — and a frozen
clock such as Symfony's `MockClock`, then assert on the `Capture` it returns or on what the store holds:

```php
$capture = $tracker->captureThrowable(new RuntimeException('Order 12 failed'));

self::assertSame(Occurrence::First, $capture?->occurrence);
self::assertSame('RuntimeException: Order 12 failed', $store->groups[$capture->fingerprint]->title);
```
