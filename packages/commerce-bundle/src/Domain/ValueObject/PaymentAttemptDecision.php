<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Whether a payment attempt may proceed now, or when it may.
 *
 * **The decision carries no reason.** The buyer sees a wait before the next attempt, never a refusal that
 * names a robot verdict; and the decision never refuses outright, since a false positive must still be able
 * to pay once the wait is over.
 */
final readonly class PaymentAttemptDecision
{
    private function __construct(
        public ?DateTimeImmutable $retryAt,
    ) {
    }

    public static function allowed(): self
    {
        return new self(null);
    }

    public static function waitUntil(DateTimeImmutable $retryAt): self
    {
        return new self($retryAt);
    }

    public function isAllowed(): bool
    {
        return !$this->retryAt instanceof DateTimeImmutable;
    }

    /**
     * The value of a `Retry-After` header, rounded up to the next whole second; zero when allowed.
     */
    public function retryAfterSeconds(DateTimeImmutable $now): int
    {
        if (!$this->retryAt instanceof DateTimeImmutable) {
            return 0;
        }

        $microseconds = ($this->retryAt->getTimestamp() - $now->getTimestamp()) * 1_000_000
            + $this->retryAt->getMicrosecond() - $now->getMicrosecond();

        // A retry time already reached or passed is no wait at all.
        return max(0, intdiv($microseconds + 999_999, 1_000_000));
    }

    /**
     * The decision over the order key and the client IP key together: the later retry wins.
     */
    public static function mostRestrictive(self $first, self ...$others): self
    {
        $result = $first;

        foreach ($others as $other) {
            if (!$other->retryAt instanceof DateTimeImmutable) {
                continue;
            }

            if (!$result->retryAt instanceof DateTimeImmutable || $other->retryAt > $result->retryAt) {
                $result = $other;
            }
        }

        return $result;
    }
}
