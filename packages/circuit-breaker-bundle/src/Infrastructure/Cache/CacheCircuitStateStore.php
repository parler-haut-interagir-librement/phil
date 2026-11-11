<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\Cache;

use DateTimeImmutable;
use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\CircuitState;
use Phil\CircuitBreaker\Contract\CircuitStateStore;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The states of the circuits in a PSR-6 cache pool shared by the application's processes: a circuit that opens in
 * one worker is open in all. An entry the pool lost, or one it holds in another shape, reads as a closed circuit.
 */
final readonly class CacheCircuitStateStore implements CircuitStateStore
{
    public const string PREFIX = 'phil_circuit_breaker.circuit.';

    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function load(CircuitKey $key): ?CircuitSnapshot
    {
        $value = $this->pool->getItem(self::PREFIX.$key->value)->get();
        $value = \is_array($value) ? $value : [];

        $state = CircuitState::tryFrom(\is_string($value['state'] ?? null) ? $value['state'] : '');
        $numbers = [$value['since'] ?? null, $value['calls'] ?? null, $value['failures'] ?? null, $value['half_open_successes'] ?? null, $value['slow_calls'] ?? null];

        if (!$state instanceof CircuitState || [true, true, true, true, true] !== array_map(is_int(...), $numbers)) {
            return null;
        }

        /** @var array{int, int, int, int, int} $numbers */
        return CircuitSnapshot::restore($state, new DateTimeImmutable('@'.$numbers[0]), $numbers[1], $numbers[2], $numbers[3], $numbers[4]);
    }

    #[Override]
    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void
    {
        $this->pool->save($this->pool->getItem(self::PREFIX.$key->value)->set([
            'state' => $snapshot->state->value,
            'since' => $snapshot->since->getTimestamp(),
            'calls' => $snapshot->calls,
            'failures' => $snapshot->failures,
            'half_open_successes' => $snapshot->halfOpenSuccesses,
            'slow_calls' => $snapshot->slowCalls,
        ]));
    }
}
