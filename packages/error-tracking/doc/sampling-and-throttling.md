# Sampling and throttling

## The sampler

`Sampler` keeps a share of the events, drawn with `Random\Randomizer`: `1.0` keeps them all, `0.25` about one in four,
`0.0` none. A rate outside 0.0 to 1.0 throws `InvalidOption`. The tracker draws before anything else, so that an event
left out costs nothing more.

```php
use Phil\ErrorTracking\Sampler;

$sampler = new Sampler(0.25);
```

A test gives the randomizer a seeded engine, such as `new Randomizer(new Mt19937(42))`, for draws it can repeat.

## The throttle

`CaptureThrottle` bounds the events kept for one key in a span of time. The tracker asks it with the fingerprint,
after the scrubber, so that an error thrown on every request writes a bounded number of rows per group. Asking counts
the event.

```php
interface CaptureThrottle
{
    public function allow(string $key): bool;
}
```

Without a throttle, every event the sampler keeps is written. `phil/error-tracking-bundle` gives one over
`symfony/rate-limiter`.
