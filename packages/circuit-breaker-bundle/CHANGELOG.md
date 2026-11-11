# Changelog

All notable changes to `phil/circuit-breaker-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilCircuitBreakerBundle` and the `phil_circuit_breaker` configuration: the clock, the store of states, the cache
  pool of the default store, and the circuits, each with its policy — its slowness threshold and slow call rate
  included — and the HTTP client to put behind it.
- `ConfiguredCircuitBreakers`: the circuits of the configuration, read once at load time so that a bad key or policy
  fails the container build with its path.
- `CacheCircuitStateStore`: the states in a PSR-6 cache pool shared by the workers, under the prefix
  `phil_circuit_breaker.circuit.`, the slow calls of the window with the other counters; an entry the pool lost or
  holds in another shape reads as a closed circuit.
- `CircuitBreakingHttpClient` and `CircuitOpen`: an HTTP client behind its circuit, a request refused before it
  leaves while the circuit is open, a `5xx` or a transport error counted as a failure, any other answer as a success;
  each request measured up to its answer or its error — the transport's `total_time`, or the monotonic time since it
  was made —, its duration recorded with the outcome so that slow requests count.
- The overrides: the services tagged `phil_circuit_breaker.override` asked together by `CircuitOverrides`, a service
  implementing `CircuitOverride` tagged by autoconfiguration.
- The parameter `phil_circuit_breaker.circuit_keys`, the keys of the declared circuits.
- `CircuitBreakerDataCollector`, `CircuitsReport` and the profiler panel of the circuits: the state of each one at
  the end of the request, and how many are not closed.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
