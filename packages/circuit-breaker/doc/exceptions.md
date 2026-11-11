# Exceptions

Every exception of the library implements `Phil\CircuitBreaker\Exception\CircuitBreakerException`.

| Exception | Extends | Thrown when |
|---|---|---|
| `InvalidCircuit` | `InvalidArgumentException` | A key outside its alphabet or longer than 87 bytes, a policy value out of bounds, a key declared twice in `CircuitBreakers`. |
| `UnknownCircuit` | `RuntimeException` | `CircuitBreakers::get()` is asked for a key no circuit is declared for. |

`InvalidCircuit` is a mistake of configuration, best caught where the configuration is read so that it fails at
start-up rather than at the first call. `UnknownCircuit` is a mistake of code: the call names a circuit nobody
declared.

A breaker never throws on an open circuit: `isAvailable()` answers `false`, and the caller chooses its fallback.
