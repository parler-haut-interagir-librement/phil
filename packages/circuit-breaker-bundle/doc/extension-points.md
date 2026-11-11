# Extension points

## The store of states

`state_store` names a service implementing `Phil\CircuitBreaker\Contract\CircuitStateStore`, which replaces
`CacheCircuitStateStore`: a key-value store, a database table. The contract — `load()` answering `null` for a state
never stored or lost, the choice of a lock — is described by `phil/circuit-breaker`.

```yaml
phil_circuit_breaker:
    state_store: App\Circuits\RedisCircuitStore
```

## The overrides

A circuit is forced open as soon as one service tagged `phil_circuit_breaker.override`
(`PhilCircuitBreakerBundle::OVERRIDE_TAG`) forces it, and never when none is tagged. A service of the application
that implements `Phil\CircuitBreaker\Contract\CircuitOverride` is tagged by autoconfiguration:

```php
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;

final readonly class MaintenanceWindows implements CircuitOverride
{
    public function __construct(
        private MaintenanceCalendar $calendar,
    ) {
    }

    public function isForcedOpen(CircuitKey $key): bool
    {
        return $this->calendar->isUnderMaintenance($key->value);
    }
}
```

A service registered without autoconfiguration carries the tag itself. The overrides are asked on every call to a
breaker, and the first that forces the circuit ends the round: an override that reads a remote source keeps its answer
for the request, and resets it between requests with `kernel.reset`.

`phil/feature-flags-bundle` tags one: a circuit is forced open while its flag, `circuit-open.<key>`, is on.

## The keys of the circuits

The parameter `phil_circuit_breaker.circuit_keys` (`PhilCircuitBreakerBundle::CIRCUIT_KEYS`) holds the keys of the
declared circuits, a list of strings, empty without circuits. A bundle that derives something per circuit — a flag, a
metric, an alert — reads it in a compiler pass, once every bundle has loaded its configuration; the keys are already
validated.
