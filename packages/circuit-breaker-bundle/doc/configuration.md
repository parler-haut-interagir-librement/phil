# Configuration

## Every key

```yaml
phil_circuit_breaker:
    # Service id of the PSR-20 clock of the windows and open durations.
    clock: clock

    # Service id of a CircuitStateStore shared by every breaker; null (the default) keeps the states in cache_pool.
    state_store: ~

    # The PSR-6 pool of the default store, read when state_store is null.
    cache_pool: cache.app

    # One circuit breaker per key.
    circuits:
        http.payments:
            failure_rate_percent: 50     # required, 1 to 100
            minimum_calls: 10            # required, at least 1
            window_seconds: 60           # required, at least 1
            open_seconds: 30             # required, at least 1
            half_open_successes: 1       # at least 1
            slow_call_milliseconds: 2000 # a call that lasts longer is slow; null (the default) counts none
            slow_call_rate_percent: 50   # 1 to 100, 100 by default
            http_client: payments.client # the client to put behind the circuit, or null
```

## `clock`

| | |
|---|---|
| Type | string (service id) |
| Default | `clock` |
| Effect | The PSR-20 clock every breaker reads. |

## `state_store`

| | |
|---|---|
| Type | string (service id) or `null` |
| Default | `null` |
| Effect | The `CircuitStateStore` shared by every breaker; `null` keeps the states in `cache_pool` through `CacheCircuitStateStore`. |

## `cache_pool`

| | |
|---|---|
| Type | string (service id) |
| Default | `cache.app` |
| Effect | The PSR-6 pool of `CacheCircuitStateStore`, when `state_store` is `null`. |

## `circuits`

A map of circuit key to policy, read by `ConfiguredCircuitBreakers`. Keys are not normalized.

| Field | Type | Default | Meaning |
|---|---|---|---|
| *(key)* | map | — | Lowercase ASCII segments separated by `.`, `_` or `-`, at most 87 bytes. |
| `failure_rate_percent` | integer | required | The share of failed calls that opens the circuit, 1 to 100. |
| `minimum_calls` | integer | required | The calls a window needs before it can open the circuit. |
| `window_seconds` | integer | required | The length of the fixed window. |
| `open_seconds` | integer | required | How long the circuit stays open before trial calls. |
| `half_open_successes` | integer | `1` | The consecutive trial successes that close it. |
| `slow_call_milliseconds` | integer or `null` | `null` | The duration a slow call exceeds, at least 1; `null` counts no call as slow. |
| `slow_call_rate_percent` | integer | `100` | The share of slow calls that opens the circuit, 1 to 100, read when there is a threshold. |
| `http_client` | string (service id) or `null` | `null` | The HTTP client the bundle decorates with `CircuitBreakingHttpClient`: see [HTTP clients](http-clients.md). |

With at least one circuit, the bundle registers `CircuitBreakers`, built by
`ConfiguredCircuitBreakers::fromConfiguration()` over the policies, the store, `CircuitOverrides` and the clock; the
default store when `state_store` is `null`; one decorated client per `http_client`; and the profiler collector.
Without circuits it registers none of them, and the parameter of the keys is an empty list.

## Errors

Every circuit is read while the container is built, so a malformed one fails `cache:clear` rather than the first call
to its third party. The message names the circuit:

| Message | Cause |
|---|---|
| `phil_circuit_breaker.circuits.<key>: "<key>" is not a circuit key: …` | A key outside the alphabet. |
| `phil_circuit_breaker.circuits.<key>: A circuit key is at most 87 characters; …` | A key too long. |
| `phil_circuit_breaker.circuits.<key>: A failure rate threshold is between 1 and 100 %; got 0.` | A rate out of bounds. |
| `phil_circuit_breaker.circuits.<key>: The window of a circuit policy is at least 1; got 0.` | A duration or count below 1. |
| `phil_circuit_breaker.state_store must be a service id.` | A service id that is not a string. |

Each is an `InvalidConfigurationException` whose previous exception is the library's `InvalidCircuit`. A missing
required field or a value that is not an integer is refused by the configuration tree first.
