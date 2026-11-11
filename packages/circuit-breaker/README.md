# phil/circuit-breaker

**One circuit breaker per third party: calls stop at once when it keeps failing, and resume when it recovers.**

phil/circuit-breaker guards the calls an application makes to a third party — an HTTP API, a webhook endpoint, a mail
relay — with a circuit per key. While the circuit is closed, calls pass and their outcomes are counted in a fixed
window; when at least a minimum of calls were made and enough of them failed, or lasted longer than the policy allows,
the circuit opens and calls go straight to their fallback, without waiting for a timeout. After its open duration, the
circuit lets trial calls through (half-open): enough successes close it, a single failure opens it again. One failing
third party only ever trips its own circuit.

It is for any PHP application that depends on services it does not control: a shop that charges cards through a
payment provider, a site that sends webhooks to partners, a tool that geocodes addresses. An operator can force a
circuit open, whatever its counters say, through an override of the application's — a feature flag, for instance.

It is a plain PHP library whose only requirement is `psr/clock`. The state machine is pure:
each transition takes a policy and an instant and returns a new snapshot, so it is tested to the second and stored as
plain values. Where the states live between requests and between processes is a port, `CircuitStateStore`, and so is
the operator's override, `CircuitOverride`.

The design is small: a `CircuitKey` names what is guarded, a `CircuitPolicy` says when it opens and how it recovers,
a `CircuitSnapshot` is the state at one instant, a `CircuitBreaker` puts them together with the store, the override
and the clock, and `CircuitBreakers` holds the breakers of an application by key.

## Status

| Part | State |
|---|---|
| Keys, policies (failure rate over a minimum of calls, fixed window, open duration, half-open successes) | Implemented and tested |
| Slow calls: an outcome with its duration, a slowness threshold and a slow call rate in the policy | Implemented and tested |
| The closed/open/half-open state machine as pure transitions | Implemented and tested |
| `CircuitBreaker` and `CircuitBreakers` over a store, an override and a PSR-20 clock | Implemented and tested |
| `CircuitOverride` and `CircuitOverrides`, the operator's switches asked together | Implemented and tested |
| A sliding window, a limit on concurrent trial calls in the half-open state | Not started: they need shared storage and a lock, and belong to a store adapter |
| A Symfony bundle: circuits from configuration, a PSR-6 store, HTTP clients behind their circuits, a profiler panel | Implemented in `phil/circuit-breaker-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, building the breakers. |
| [Circuits](doc/circuits.md) | Keys, policies, the state machine and its transitions. |
| [Using a breaker](doc/using-a-breaker.md) | `CircuitBreaker`, `CircuitBreakers`, what counts as a failure, the fallback. |
| [Extension points](doc/extension-points.md) | The store of states and the overrides of the application. |
| [Exceptions](doc/exceptions.md) | `CircuitBreakerException` and its two exceptions. |
| [Testing](doc/testing.md) | The library's tests, mutation bar and benchmark, and how to test code behind a breaker. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/circuit-breaker
```

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Symfony\Component\Clock\NativeClock;

$payments = CircuitKey::of('http.payments');
$breakers = new CircuitBreakers([
    new CircuitBreaker($payments, CircuitPolicy::of(50, 10, 60, 30), $store, new CircuitOverrides(), new NativeClock()),
]);

$breaker = $breakers->get($payments);

if (!$breaker->isAvailable()) {
    return $fallback;                // open: no call, no timeout
}

try {
    $answer = $client->charge($order);
} catch (PaymentsUnreachable $unreachable) {
    $breaker->recordFailure();

    return $fallback;
}

$breaker->recordSuccess();
```

`$store` is the application's `CircuitStateStore`: see [Extension points](doc/extension-points.md).

## Requirements

PHP 8.5 or later and `psr/clock`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
