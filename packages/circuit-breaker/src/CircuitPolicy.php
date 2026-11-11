<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker;

use Phil\CircuitBreaker\Exception\InvalidCircuit;

/**
 * When a circuit opens and how it recovers. The values are the application's, one policy per third party.
 *
 * - The circuit opens when, within a fixed window of `windowSeconds`, at least `minimumCalls` calls were
 *   made and at least `failureRatePercent` % of them failed. **The minimum is the denominator**: without it,
 *   one failure out of one call is a 100 % failure rate, and an isolated error trips a healthy third party.
 * - With a slowness threshold, `slowCallMilliseconds`, a call that lasts longer is slow, whether it succeeded or
 *   failed; the circuit also opens when at least `slowCallRatePercent` % of the window's calls were slow.
 * - It stays open for `openSeconds`, then lets trial calls through (half-open).
 * - `halfOpenSuccesses` consecutive successes close it; one failure, or one slow call, opens it again for another
 *   full period.
 */
final readonly class CircuitPolicy
{
    private function __construct(
        public int $failureRatePercent,
        public int $minimumCalls,
        public int $windowSeconds,
        public int $openSeconds,
        public int $halfOpenSuccesses,
        public ?int $slowCallMilliseconds,
        public int $slowCallRatePercent,
    ) {
    }

    /**
     * @param ?int $slowCallMilliseconds the duration a slow call exceeds, at least 1; `null` counts no call as slow
     * @param int  $slowCallRatePercent  the share of slow calls that opens the circuit, 1 to 100
     *
     * @throws InvalidCircuit
     */
    public static function of(
        int $failureRatePercent,
        int $minimumCalls,
        int $windowSeconds,
        int $openSeconds,
        int $halfOpenSuccesses = 1,
        ?int $slowCallMilliseconds = null,
        int $slowCallRatePercent = 100,
    ): self {
        foreach (['failure' => $failureRatePercent, 'slow call' => $slowCallRatePercent] as $what => $rate) {
            if ($rate < 1 || $rate > 100) {
                throw InvalidCircuit::because(\sprintf('A %s rate threshold is between 1 and 100 %%; got %d.', $what, $rate));
            }
        }

        foreach ([
            'minimum number of calls' => $minimumCalls,
            'window' => $windowSeconds,
            'open duration' => $openSeconds,
            'number of half-open successes' => $halfOpenSuccesses,
            'slowness threshold' => $slowCallMilliseconds ?? 1,
        ] as $what => $value) {
            if ($value < 1) {
                throw InvalidCircuit::because(\sprintf('The %s of a circuit policy is at least 1; got %d.', $what, $value));
            }
        }

        return new self($failureRatePercent, $minimumCalls, $windowSeconds, $openSeconds, $halfOpenSuccesses, $slowCallMilliseconds, $slowCallRatePercent);
    }

    /**
     * Integer arithmetic, so that 1 failure out of 3 calls against a 33 % threshold is not decided by a float. Slow
     * calls trip the circuit only when the policy has a slowness threshold.
     */
    public function trips(int $calls, int $failures, int $slowCalls = 0): bool
    {
        return $calls >= $this->minimumCalls && (
            $failures * 100 >= $this->failureRatePercent * $calls
            || (null !== $this->slowCallMilliseconds && $slowCalls * 100 >= $this->slowCallRatePercent * $calls)
        );
    }

    /**
     * Whether a call lasted longer than the slowness threshold; never without a threshold or a measured duration.
     */
    public function isSlow(CallOutcome $outcome): bool
    {
        return null !== $this->slowCallMilliseconds && null !== $outcome->milliseconds && $outcome->milliseconds > $this->slowCallMilliseconds;
    }
}
