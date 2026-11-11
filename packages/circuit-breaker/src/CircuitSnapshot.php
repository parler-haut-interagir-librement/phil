<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use DateTimeImmutable;

/**
 * The state of one circuit at one instant, and its transitions as pure functions of a policy and a time.
 *
 * Each transition returns a new snapshot; none reads a clock. Time enters as an argument, which is what lets
 * the state machine be tested to the second and stored by an adapter as plain values.
 *
 * The window is fixed, not sliding: it restarts `windowSeconds` after it began. A sliding window, or a limit
 * on concurrent trial calls in the half-open state, needs shared storage and a lock, and belongs to the
 * adapter that provides them.
 */
final readonly class CircuitSnapshot
{
    private function __construct(
        public CircuitState $state,
        public DateTimeImmutable $since,
        public int $calls,
        public int $failures,
        public int $halfOpenSuccesses,
        public int $slowCalls = 0,
    ) {
    }

    public static function closed(DateTimeImmutable $now): self
    {
        return new self(CircuitState::Closed, $now, 0, 0, 0);
    }

    /**
     * Rebuilds a stored snapshot. `$since` is the start of the window when closed, the instant the circuit
     * opened when open, and the instant it turned half-open when half-open.
     */
    public static function restore(CircuitState $state, DateTimeImmutable $since, int $calls, int $failures, int $halfOpenSuccesses, int $slowCalls = 0): self
    {
        return new self($state, $since, max(0, $calls), max(0, min($failures, $calls)), max(0, $halfOpenSuccesses), max(0, min($slowCalls, $calls)));
    }

    /**
     * Applies the transitions that only time causes: an open circuit turns half-open once its open duration
     * has elapsed, and a closed circuit starts a fresh window once its window has elapsed.
     */
    public function at(CircuitPolicy $policy, DateTimeImmutable $now): self
    {
        $elapsed = $now->getTimestamp() - $this->since->getTimestamp();

        return match ($this->state) {
            CircuitState::Open => $elapsed >= $policy->openSeconds ? new self(CircuitState::HalfOpen, $now, 0, 0, 0) : $this,
            CircuitState::Closed => $elapsed >= $policy->windowSeconds ? self::closed($now) : $this,
            CircuitState::HalfOpen => $this,
        };
    }

    public function permitsCalls(): bool
    {
        return CircuitState::Open !== $this->state;
    }

    /**
     * Counts one call: a failure, or a call slower than the policy's threshold, may open the circuit; while half-open,
     * either opens it again, and only a call both successful and not slow counts towards closing it.
     */
    public function record(CircuitPolicy $policy, CallOutcome $outcome, DateTimeImmutable $now): self
    {
        $current = $this->at($policy, $now);
        $slow = $policy->isSlow($outcome);
        $bad = $outcome->failed || $slow;

        return match ($current->state) {
            CircuitState::Closed => $current->counted($policy, $now, $outcome->failed, $slow, $bad),
            CircuitState::HalfOpen => match (true) {
                $bad => $this->opened($now),
                $current->halfOpenSuccesses + 1 >= $policy->halfOpenSuccesses => self::closed($now),
                default => new self(CircuitState::HalfOpen, $current->since, 0, 0, $current->halfOpenSuccesses + 1),
            },
            // A call that went through while open (a race with another process) changes nothing.
            CircuitState::Open => $current,
        };
    }

    public function recordSuccess(CircuitPolicy $policy, DateTimeImmutable $now): self
    {
        return $this->record($policy, CallOutcome::succeeded(), $now);
    }

    public function recordFailure(CircuitPolicy $policy, DateTimeImmutable $now): self
    {
        return $this->record($policy, CallOutcome::failed(), $now);
    }

    /**
     * One more call in the window of a closed circuit, which opens when a bad call brings a rate to its threshold.
     */
    private function counted(CircuitPolicy $policy, DateTimeImmutable $now, bool $failed, bool $slow, bool $bad): self
    {
        $calls = $this->calls + 1;
        $failures = $this->failures + ($failed ? 1 : 0);
        $slowCalls = $this->slowCalls + ($slow ? 1 : 0);

        return $bad && $policy->trips($calls, $failures, $slowCalls)
            ? $this->opened($now)
            : new self(CircuitState::Closed, $this->since, $calls, $failures, 0, $slowCalls);
    }

    private function opened(DateTimeImmutable $now): self
    {
        return new self(CircuitState::Open, $now, 0, 0, 0);
    }
}
