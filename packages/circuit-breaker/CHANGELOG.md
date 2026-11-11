# Changelog

All notable changes to `phil/circuit-breaker` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `CircuitKey`: what one breaker guards, lowercase ASCII segments separated by `.`, `_` or `-`, at most 87 bytes.
- `CircuitPolicy`: a failure rate threshold from 1 to 100 %, a minimum of calls that is the denominator, a fixed
  window, an open duration and the half-open successes that close the circuit; an optional slowness threshold in
  milliseconds and a slow call rate, so that calls lasting longer than the threshold open the circuit too; `trips()`
  in integer arithmetic, `isSlow()`.
- `CallOutcome`: how one call went, `succeeded()` or `failed()`, with its duration in milliseconds when the caller
  measured it.
- `CircuitState` and `CircuitSnapshot`: the closed/open/half-open state machine as pure transitions of a policy and
  an instant — `at()`, `record()`, `recordSuccess()`, `recordFailure()`, `permitsCalls()` —, `closed()` and
  `restore()`; the slow calls counted with the calls and failures of the window, a slow trial call opening the circuit
  again.
- `CircuitStateStore`, the port of the states shared by the processes; a state never stored or lost is closed.
- `CircuitOverride`, the port of the operator who forces a circuit open; `CircuitOverrides`, several overrides asked
  together, none forcing anything when empty.
- `CircuitBreaker`: `isAvailable()`, `state()`, `record()`, `recordSuccess()` and `recordFailure()` over a store, an
  override and a PSR-20 clock, the time-driven transitions saved only when they change something, nothing recorded
  while forced open; `CircuitBreakers`, the breakers by key, each key declared once.
- `CircuitBreakerException`, implemented by `InvalidCircuit` and `UnknownCircuit`.
- The benchmark of a breaker's ask and report, timed or not, a transition and a key.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
