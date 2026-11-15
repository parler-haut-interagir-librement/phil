<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Service;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptDecision;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptPolicy;
use Phil\CommerceBundle\Domain\ValueObject\PaymentAttemptState;

/**
 * The payment attempt limiter, as pure functions over one key's single state.
 *
 * - `decide()` says whether an attempt may proceed now: an absent or expired state allows it; a spent budget
 *   waits until the state expires; otherwise the attempt waits until the cumulative wait has elapsed since the
 *   last attempt.
 * - `record()` returns the state after an attempt. When the application penalises the attempt — because a
 *   robot verdict came with it and it chose friction over a stronger authentication challenge — one penalty
 *   step joins the cumulative wait. **The verdict itself is not an argument and is never kept.**
 * - **An attempt made during a wait is not recorded.** `record()` returns the state unchanged when `decide()`
 *   would make the attempt wait: otherwise each retry during the wait — a buyer refreshing, a neighbour behind
 *   the same address — would push the last attempt forward, and the wait would never end. A false positive must
 *   still be able to pay once its wait is over.
 *
 * The caller stores the returned state with the policy's window as its time-to-live (`PaymentAttemptStateStore`).
 */
final readonly class PaymentAttemptLimiter
{
    public function __construct(
        private PaymentAttemptPolicy $policy,
    ) {
    }

    public function decide(?PaymentAttemptState $state, DateTimeImmutable $now): PaymentAttemptDecision
    {
        if (!$state instanceof PaymentAttemptState || $state->isExpiredAt($now, $this->policy)) {
            return PaymentAttemptDecision::allowed();
        }

        if ($state->attempts >= $this->policy->budget) {
            return PaymentAttemptDecision::waitUntil($state->expiresAt($this->policy));
        }

        $nextAllowedAt = $state->nextAttemptAllowedAt();

        return $now < $nextAllowedAt ? PaymentAttemptDecision::waitUntil($nextAllowedAt) : PaymentAttemptDecision::allowed();
    }

    /**
     * The cumulative wait is capped at `budget × step`, the bound the window is guaranteed to exceed. An
     * attempt that `decide()` makes wait leaves the state as it is.
     */
    public function record(?PaymentAttemptState $state, DateTimeImmutable $now, bool $penalise): PaymentAttemptState
    {
        if ($state instanceof PaymentAttemptState && !$this->decide($state, $now)->isAllowed()) {
            return $state;
        }

        $fresh = !$state instanceof PaymentAttemptState || $state->isExpiredAt($now, $this->policy);
        $attempts = $fresh ? 0 : $state->attempts;
        $wait = $fresh ? 0 : $state->cumulativeWaitSeconds;

        if ($penalise) {
            $wait = min($wait + $this->policy->penaltyStepSeconds, $this->policy->longestCumulativeWaitSeconds());
        }

        return PaymentAttemptState::of($attempts + 1, $wait, $now);
    }

    public function timeToLiveSeconds(): int
    {
        return $this->policy->windowSeconds;
    }
}
