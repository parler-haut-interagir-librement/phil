<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Contract;

use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitSnapshot;

/**
 * Where the state of each circuit lives between requests and between processes: a cache pool, a key-value
 * store. The adapter belongs to the application, and so does the choice of whether a read and the write that
 * follows it are made atomic by a lock.
 *
 * A store that loses its state closes every circuit, which is the safe direction: calls resume and the
 * circuit trips again if the third party is still failing.
 */
interface CircuitStateStore
{
    public function load(CircuitKey $key): ?CircuitSnapshot;

    public function save(CircuitKey $key, CircuitSnapshot $snapshot): void;
}
