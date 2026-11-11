<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit\Fixture;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\Contract\CircuitStateStore;

final class InMemoryCircuitStore implements CircuitStateStore
{
    /** @var array<string, CircuitSnapshot> */
    public array $snapshots = [];

    public int $saves = 0;

    #[Override]
    public function load(CircuitKey $key): ?CircuitSnapshot
    {
        return $this->snapshots[$key->value] ?? null;
    }

    #[Override]
    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void
    {
        ++$this->saves;
        $this->snapshots[$key->value] = $snapshot;
    }
}
