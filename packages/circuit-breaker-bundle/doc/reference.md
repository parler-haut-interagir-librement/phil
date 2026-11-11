# Reference

## Classes

| Class | Role |
|---|---|
| `Phil\CircuitBreakerBundle\PhilCircuitBreakerBundle` | The bundle, its configuration and its services; `OVERRIDE_TAG`, `CIRCUIT_KEYS`, `HTTP_CLIENT_PREFIX` |
| `Phil\CircuitBreakerBundle\Infrastructure\Configuration\ConfiguredCircuitBreakers` | `CircuitBreakers` from the configuration; `validate()` at load time |
| `Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore` | The states in a PSR-6 pool, under `PREFIX` |
| `Phil\CircuitBreakerBundle\Infrastructure\HttpClient\CircuitBreakingHttpClient` | An HTTP client behind its circuit |
| `Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen` | A request not sent because its circuit is open; a transport exception |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector` | The state of each circuit in the profiler: `circuits()`, `trippedCount()`, `TEMPLATE` |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitsReport` | What one request collected |

## Services, tag and parameter

| Id | Registered when | What it is |
|---|---|---|
| `Phil\CircuitBreaker\CircuitBreakers` | a circuit is declared | The breakers by key, to inject where a call is guarded |
| `Phil\CircuitBreaker\CircuitOverrides` | a circuit is declared | The tagged overrides, asked together |
| `Phil\CircuitBreakerBundle\Infrastructure\Cache\CacheCircuitStateStore` | a circuit is declared and `state_store` is `null` | The default store |
| `phil_circuit_breaker.http_client.<key>` | a circuit has `http_client` | The decorator of that client |
| `Phil\CircuitBreakerBundle\Infrastructure\Profiler\CircuitBreakerDataCollector` | a circuit is declared | The collector, tagged `data_collector` with the id `phil_circuit_breaker` |
| tag `phil_circuit_breaker.override` | always | The overrides; given by autoconfiguration to every `CircuitOverride` |
| parameter `phil_circuit_breaker.circuit_keys` | always | The keys of the declared circuits, a list of strings |

## Template

`@PhilCircuitBreaker/profiler/collector.html.twig`: the toolbar item — the circuits not closed out of all, each with
its state — and the panel, a table of the circuits and their states.
