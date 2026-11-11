<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Contract;

use Phil\CircuitBreaker\CircuitKey;

/**
 * Whether an operator has forced a circuit open, whatever its counters say.
 *
 * The adapter belongs to the application: a feature flag per key, so that forcing a circuit open goes through the
 * same audited toggle as any flag, or any other switch an operator controls. A forced circuit stays confined to its
 * own key: it never stops a whole subsystem. {@see \Phil\CircuitBreaker\CircuitOverrides} asks several adapters, and
 * none at all when no operator can force a circuit.
 */
interface CircuitOverride
{
    public function isForcedOpen(CircuitKey $key): bool;
}
