<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The single state of one limiter key: its attempts, its cumulative wait, and when the last attempt happened.
 *
 * **Nothing else.** The state never holds a robot verdict, and there is no separate counter of penalties: a
 * penalty melts into the cumulative wait, so the state cannot tell why the wait went up. It lives as long as
 * the policy's window after its last attempt, and is then gone.
 */
final readonly class PaymentAttemptState
{
    private function __construct(
        public int $attempts,
        public int $cumulativeWaitSeconds,
        public DateTimeImmutable $lastAttemptAt,
    ) {
    }

    /**
     * For reading a stored state back.
     */
    public static function of(int $attempts, int $cumulativeWaitSeconds, DateTimeImmutable $lastAttemptAt): self
    {
        if ($attempts < 1) {
            throw InvalidValue::because(\sprintf('A limiter state exists only after an attempt; %d attempts is not one.', $attempts));
        }

        if ($cumulativeWaitSeconds < 0) {
            throw InvalidValue::because(\sprintf('A cumulative wait is never negative; %d is.', $cumulativeWaitSeconds));
        }

        return new self($attempts, $cumulativeWaitSeconds, $lastAttemptAt);
    }

    public function expiresAt(PaymentAttemptPolicy $policy): DateTimeImmutable
    {
        return $this->lastAttemptAt->add(new DateInterval(\sprintf('PT%dS', $policy->windowSeconds)));
    }

    public function isExpiredAt(DateTimeImmutable $now, PaymentAttemptPolicy $policy): bool
    {
        return $now >= $this->expiresAt($policy);
    }

    public function nextAttemptAllowedAt(): DateTimeImmutable
    {
        return $this->lastAttemptAt->add(new DateInterval(\sprintf('PT%dS', $this->cumulativeWaitSeconds)));
    }
}
