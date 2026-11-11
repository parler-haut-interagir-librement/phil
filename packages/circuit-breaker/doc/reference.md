# Reference

| Type | Kind | Role |
|---|---|---|
| `Phil\CircuitBreaker\CircuitKey` | `final readonly class` | What one breaker guards: `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\CircuitBreaker\CircuitPolicy` | `final readonly class` | When a circuit opens and how it recovers: `of()`, `trips()` |
| `Phil\CircuitBreaker\CircuitState` | enum | `closed`, `open`, `half_open` |
| `Phil\CircuitBreaker\CircuitSnapshot` | `final readonly class` | The state at one instant and its pure transitions: `closed()`, `restore()`, `at()`, `record()`, `recordSuccess()`, `recordFailure()`, `permitsCalls()` |
| `Phil\CircuitBreaker\CircuitBreaker` | `final readonly class` | One breaker: `isAvailable()`, `state()`, `record()`, `recordSuccess()`, `recordFailure()` |
| `Phil\CircuitBreaker\CallOutcome` | `final readonly class` | How one call went: `succeeded()` or `failed()`, with its duration in milliseconds when it was measured |
| `Phil\CircuitBreaker\CircuitBreakers` | `final readonly class` | The breakers of an application by key: `get()`, `has()`, `keys()` |
| `Phil\CircuitBreaker\CircuitOverrides` | `final readonly class` | Several overrides asked together; none forcing anything when empty |
| `Phil\CircuitBreaker\Contract\CircuitStateStore` | interface | Where the states live: `load()`, `save()` |
| `Phil\CircuitBreaker\Contract\CircuitOverride` | interface | Whether an operator forced a circuit open: `isForcedOpen()` |
| `Phil\CircuitBreaker\Exception\CircuitBreakerException` | interface | Every exception of the library |
| `Phil\CircuitBreaker\Exception\InvalidCircuit` | `final class` | A key, a policy or a registry the library cannot work with |
| `Phil\CircuitBreaker\Exception\UnknownCircuit` | `final class` | A breaker asked for an undeclared key |
