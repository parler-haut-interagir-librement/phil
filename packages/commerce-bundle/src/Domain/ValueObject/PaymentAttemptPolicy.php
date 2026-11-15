<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The three values of the payment attempt limiter: the budget, the penalty step and the window.
 *
 * - **The budget** is the number of attempts a key may make within its window.
 * - **The penalty step** is the wait one penalised attempt adds to the key's cumulative wait.
 * - **The window** is the time-to-live of a key's state, renewed by each attempt.
 *
 * **The window must exceed the longest cumulative wait**, `budget × step`: otherwise a state could expire
 * before its penalty has slowed anything down. The values themselves are the application's decision.
 */
final readonly class PaymentAttemptPolicy
{
    private function __construct(
        public int $budget,
        public int $penaltyStepSeconds,
        public int $windowSeconds,
    ) {
    }

    public static function of(int $budget, int $penaltyStepSeconds, int $windowSeconds): self
    {
        if ($budget < 1) {
            throw InvalidValue::because(\sprintf('The attempt budget is at least 1; %d is not.', $budget));
        }

        if ($penaltyStepSeconds < 1) {
            throw InvalidValue::because(\sprintf('The penalty step is at least one second; %d is not.', $penaltyStepSeconds));
        }

        if ($budget > intdiv(\PHP_INT_MAX, $penaltyStepSeconds)) {
            throw InvalidValue::because('The budget times the penalty step leaves the range of a native integer.');
        }

        if ($windowSeconds <= $budget * $penaltyStepSeconds) {
            throw InvalidValue::because(\sprintf('The window (%d s) must exceed the longest cumulative wait, budget × step (%d s).', $windowSeconds, $budget * $penaltyStepSeconds));
        }

        return new self($budget, $penaltyStepSeconds, $windowSeconds);
    }

    public function longestCumulativeWaitSeconds(): int
    {
        return $this->budget * $this->penaltyStepSeconds;
    }
}
