# Extension points

The library has two ports, both interfaces of `Phil\CircuitBreaker\Contract`. Their adapters belong to the
application.

## `CircuitStateStore`

Where the state of each circuit lives between requests and between processes: a cache pool, a key-value store.

```php
interface CircuitStateStore
{
    public function load(CircuitKey $key): ?CircuitSnapshot;

    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void;
}
```

- `load()` answers `null` for a circuit never stored, or whose state was lost: the breaker reads it as a closed
  circuit. That is the safe direction: calls resume, and the circuit trips again if the third party is still failing.
- `save()` keeps the snapshot as plain values — its state, `since` as a timestamp, its three counters — and
  `CircuitSnapshot::restore()` rebuilds it.
- Whether a read and the write that follows it are made atomic by a lock is the adapter's decision. Without one, two
  processes may each count an outcome on the same snapshot and one count is lost; the circuit still opens, a call or
  two later.

A store over a key-value client:

```php
use DateTimeImmutable;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Contract\CircuitStateStore;

final readonly class KeyValueCircuitStore implements CircuitStateStore
{
    public function __construct(
        private KeyValueClient $client,
    ) {
    }

    public function load(CircuitKey $key): ?CircuitSnapshot
    {
        $value = $this->client->get('circuit.'.$key->value);
        $state = \is_array($value) ? CircuitState::tryFrom((string) ($value['state'] ?? '')) : null;

        if (null === $state) {
            return null;
        }

        return CircuitSnapshot::restore($state, new DateTimeImmutable('@'.(int) $value['since']), (int) $value['calls'], (int) $value['failures'], (int) $value['half_open_successes'], (int) ($value['slow_calls'] ?? 0));
    }

    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void
    {
        $this->client->set('circuit.'.$key->value, [
            'state' => $snapshot->state->value,
            'since' => $snapshot->since->getTimestamp(),
            'calls' => $snapshot->calls,
            'failures' => $snapshot->failures,
            'half_open_successes' => $snapshot->halfOpenSuccesses,
            'slow_calls' => $snapshot->slowCalls,
        ]);
    }
}
```

`phil/circuit-breaker-bundle` ships a store over a PSR-6 cache pool.

## `CircuitOverride`

Whether an operator has forced a circuit open, whatever its counters say:

```php
interface CircuitOverride
{
    public function isForcedOpen(CircuitKey $key): bool;
}
```

The usual adapter reads a switch an operator controls — a feature flag per key, so that forcing a circuit open goes
through an audited toggle. A forced circuit stays confined to its own key: it never stops a whole subsystem. The
breaker asks the override on every call, so an adapter that reads a remote source keeps its answer for the request.

`CircuitOverrides` asks several overrides in their order: a circuit is forced open as soon as one of them forces it,
and the ones after it are not asked. With none, no circuit is ever forced open.

```php
$overrides = new CircuitOverrides([$flagOverride, $maintenanceWindow]);
```
