<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Phil\CircuitBreaker\Contract\CircuitOverride;
use Phil\CircuitBreaker\Contract\CircuitStateStore;
use Psr\Clock\ClockInterface;

/**
 * One circuit breaker, for one key: ask `isAvailable()` before the call, send the call to its fallback when
 * it answers `false`, and report the outcome with `record()` — its duration included, so that slow calls count —,
 * or with `recordSuccess()` or `recordFailure()` when it was not measured.
 *
 * A circuit forced open by its override is unavailable whatever its counters say, and records nothing: the
 * counters resume where they were when the override is lifted.
 */
final readonly class CircuitBreaker
{
    public function __construct(
        public CircuitKey $key,
        private CircuitPolicy $policy,
        private CircuitStateStore $store,
        private CircuitOverride $override,
        private ClockInterface $clock,
    ) {
    }

    public function isAvailable(): bool
    {
        if ($this->override->isForcedOpen($this->key)) {
            return false;
        }

        return $this->advance()->permitsCalls();
    }

    public function state(): CircuitState
    {
        if ($this->override->isForcedOpen($this->key)) {
            return CircuitState::Open;
        }

        return $this->advance()->state;
    }

    /**
     * Records how a call went, its duration counted against the policy's slowness threshold when it was measured.
     */
    public function record(CallOutcome $outcome): void
    {
        if ($this->override->isForcedOpen($this->key)) {
            return;
        }

        $this->store->save($this->key, $this->load()->record($this->policy, $outcome, $this->clock->now()));
    }

    public function recordSuccess(): void
    {
        $this->record(CallOutcome::succeeded());
    }

    public function recordFailure(): void
    {
        $this->record(CallOutcome::failed());
    }

    /**
     * Applies the time-driven transitions and stores them only when they changed something.
     */
    private function advance(): CircuitSnapshot
    {
        $current = $this->load();
        $advanced = $current->at($this->policy, $this->clock->now());

        if ($advanced !== $current) {
            $this->store->save($this->key, $advanced);
        }

        return $advanced;
    }

    private function load(): CircuitSnapshot
    {
        return $this->store->load($this->key) ?? CircuitSnapshot::closed($this->clock->now());
    }
}
