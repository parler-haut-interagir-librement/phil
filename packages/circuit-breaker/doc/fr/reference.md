<!-- phil-dev source-sha256=b583f7bbdd96aa9fd8f6ff8ffda27026f09306fc6f8ae26939df16b50518d9cc -->
# Référence

| Type | Genre | Rôle |
|---|---|---|
| `Phil\CircuitBreaker\CircuitKey` | `final readonly class` | Ce que protège un disjoncteur : `of()`, `equals()`, `MAX_LENGTH` |
| `Phil\CircuitBreaker\CircuitPolicy` | `final readonly class` | Quand un circuit s'ouvre et comment il se rétablit : `of()`, `trips()` |
| `Phil\CircuitBreaker\CircuitState` | énumération | `closed`, `open`, `half_open` |
| `Phil\CircuitBreaker\CircuitSnapshot` | `final readonly class` | L'état à un instant et ses transitions pures : `closed()`, `restore()`, `at()`, `record()`, `recordSuccess()`, `recordFailure()`, `permitsCalls()` |
| `Phil\CircuitBreaker\CircuitBreaker` | `final readonly class` | Un disjoncteur : `isAvailable()`, `state()`, `record()`, `recordSuccess()`, `recordFailure()` |
| `Phil\CircuitBreaker\CallOutcome` | `final readonly class` | Comment s'est passé un appel : `succeeded()` ou `failed()`, avec sa durée en millisecondes quand elle a été mesurée |
| `Phil\CircuitBreaker\CircuitBreakers` | `final readonly class` | Les disjoncteurs d'une application par clé : `get()`, `has()`, `keys()` |
| `Phil\CircuitBreaker\CircuitOverrides` | `final readonly class` | Plusieurs forçages interrogés ensemble ; vide, il ne force rien |
| `Phil\CircuitBreaker\Contract\CircuitStateStore` | interface | L'endroit où vivent les états : `load()`, `save()` |
| `Phil\CircuitBreaker\Contract\CircuitOverride` | interface | Si un opérateur a forcé l'ouverture d'un circuit : `isForcedOpen()` |
| `Phil\CircuitBreaker\Exception\CircuitBreakerException` | interface | Chaque exception de la bibliothèque |
| `Phil\CircuitBreaker\Exception\InvalidCircuit` | `final class` | Une clé, une politique ou un registre dont la bibliothèque ne peut rien faire |
| `Phil\CircuitBreaker\Exception\UnknownCircuit` | `final class` | Un disjoncteur demandé pour une clé non déclarée |
