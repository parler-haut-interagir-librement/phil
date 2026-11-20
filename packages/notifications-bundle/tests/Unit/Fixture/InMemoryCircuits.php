<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\Contract\CircuitStateStore;

/**
 * The states of the circuits, in memory.
 */
final class InMemoryCircuits implements CircuitStateStore
{
    /** @var array<string, CircuitSnapshot> */
    public array $snapshots = [];

    #[Override]
    public function load(CircuitKey $key): ?CircuitSnapshot
    {
        return $this->snapshots[$key->value] ?? null;
    }

    #[Override]
    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void
    {
        $this->snapshots[$key->value] = $snapshot;
    }
}
