# Using a breaker

## The calls around a third party

Ask the breaker before the call, send the call to its fallback when it answers `false`, and report the outcome:

```php
use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;

final readonly class Geocoder
{
    public function __construct(
        private CircuitBreakers $circuits,
        private GeocodingApi $api,
    ) {
    }

    public function locate(string $address): ?Position
    {
        $breaker = $this->circuits->get(CircuitKey::of('http.geocoding'));

        if (!$breaker->isAvailable()) {
            return null;                      // the fallback: a cached answer, a queue, a message
        }

        $started = hrtime(true);

        try {
            $position = $this->api->locate($address);
        } catch (GeocodingUnavailable) {
            $breaker->record(CallOutcome::failed((int) ((hrtime(true) - $started) / 1_000_000)));

            return null;
        }

        $breaker->record(CallOutcome::succeeded((int) ((hrtime(true) - $started) / 1_000_000)));

        return $position;
    }
}
```

Which outcomes count as failures is the caller's decision: a timeout or a server error is the third party's failure,
an answer that refuses a malformed request usually is not. So is the fallback each call goes to, and the alert an
open circuit raises. The duration, measured on a monotonic clock such as `hrtime()`, lets a policy with a slowness
threshold count the slow calls; `recordSuccess()` and `recordFailure()` report an outcome that was not measured.

## `CircuitBreaker`

| Member | Effect |
|---|---|
| `key` | The public `CircuitKey`. |
| `isAvailable(): bool` | `false` when forced open or open. Applies the time-driven transitions first and saves them only when they changed something. |
| `state(): CircuitState` | `Open` when forced open; otherwise the state after the time-driven transitions. |
| `record(CallOutcome $outcome): void` | Records how a call went, its duration counted against the policy's slowness threshold, and saves the new snapshot; does nothing while forced open. |
| `recordSuccess(): void` | Records a success and saves the new snapshot; does nothing while forced open. |
| `recordFailure(): void` | Records a failure and saves the new snapshot; does nothing while forced open. |

A circuit forced open by its override is unavailable whatever its counters say, and records nothing: its counters
resume where they were when the override is lifted.

## `CircuitBreakers`

| Method | Returns |
|---|---|
| `get(CircuitKey $key): CircuitBreaker` | The breaker; an undeclared key throws `UnknownCircuit`. A call to a third party never gets an undeclared circuit by default: its policy is a decision, written with the call. |
| `has(CircuitKey $key): bool` | Whether the key is declared. |
| `keys(): list<CircuitKey>` | The declared keys, in their order. |

A key declared twice throws `InvalidCircuit` when the registry is built.
