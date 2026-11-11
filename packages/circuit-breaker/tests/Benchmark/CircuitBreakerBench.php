<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Benchmark;

use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\CircuitPolicy;
use Phil\CircuitBreaker\CircuitSnapshot;
use Phil\CircuitBreaker\Tests\Unit\Fixture\FrozenClock;
use Phil\CircuitBreaker\Tests\Unit\Fixture\InMemoryCircuitStore;

/**
 * Every call to a third party asks its breaker first and reports its outcome after: the ask reads the store and
 * applies the time-driven transitions, the report reads, counts and writes.
 */
final readonly class CircuitBreakerBench
{
    private CircuitBreaker $breaker;

    private CircuitPolicy $policy;

    private FrozenClock $clock;

    private CircuitSnapshot $snapshot;

    public function __construct()
    {
        $this->clock = new FrozenClock();
        $this->policy = CircuitPolicy::of(50, 10, 60, 30, 1, 500, 50);
        $this->breaker = new CircuitBreaker(CircuitKey::of('http.payments'), $this->policy, new InMemoryCircuitStore(), new CircuitOverrides(), $this->clock);
        $this->snapshot = CircuitSnapshot::closed($this->clock->now());
    }

    public function benchIsAvailable(): void
    {
        $this->breaker->isAvailable();
    }

    public function benchCallReported(): void
    {
        if ($this->breaker->isAvailable()) {
            $this->breaker->recordSuccess();
        }
    }

    public function benchTimedCallReported(): void
    {
        if ($this->breaker->isAvailable()) {
            $this->breaker->record(CallOutcome::succeeded(120));
        }
    }

    public function benchSnapshotTransition(): void
    {
        $this->snapshot->recordFailure($this->policy, $this->clock->now());
    }

    public function benchKey(): void
    {
        CircuitKey::of('http.payments');
    }
}
