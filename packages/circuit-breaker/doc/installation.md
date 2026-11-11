# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | Readonly classes, typed constants, `new` without parentheses. |
| `psr/clock` | `^1.0` | The windows and open durations are measured on a PSR-20 clock. |

## Installing

```sh
composer require phil/circuit-breaker
```

## Building the breakers

A breaker needs its key, its policy, a store of states, an override and a clock. The store and the override are the
application's ([Extension points](extension-points.md)); `new CircuitOverrides()` is the override of an application
where no operator forces a circuit.

```php
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;

$overrides = new CircuitOverrides([$operatorSwitch]);

$breakers = new CircuitBreakers([
    new CircuitBreaker(CircuitKey::of('http.payments'), CircuitPolicy::of(50, 10, 60, 30), $store, $overrides, $clock),
    new CircuitBreaker(CircuitKey::of('webhook.partner-a'), CircuitPolicy::of(80, 5, 300, 120), $store, $overrides, $clock),
]);
```

The breakers keep no state of their own between calls: every ask reads the store, every report writes it. They are
safe to share across the requests of a long-running worker.

In Symfony, `phil/circuit-breaker-bundle` builds them from configuration, with a store over a PSR-6 cache pool.
