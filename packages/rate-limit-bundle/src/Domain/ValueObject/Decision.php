<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Domain\ValueObject;

use Phil\RateLimitBundle\Domain\Exception\InvalidRateLimit;

/**
 * What a limiter answered for one consumption: accepted or refused, its limit, what is left, and the seconds until it
 * resets and until a refused request may pass.
 *
 * `limit` is null for a limiter that does not limit, which then writes no header. `window` is the length in seconds
 * of the limiter's window, when its policy has one.
 */
final readonly class Decision
{
    /**
     * @throws InvalidRateLimit when a count or a duration is negative, or a limit below one
     */
    public function __construct(
        public bool $accepted,
        public ?int $limit,
        public int $remaining,
        public int $resetAfter,
        public int $retryAfter = 0,
        public ?int $window = null,
        /** The name of the limiter that decided, which the headers name the policy by. */
        public string $limiter = '',
    ) {
        if ($remaining < 0 || $resetAfter < 0 || $retryAfter < 0 || (null !== $window && $window < 1) || (null !== $limit && $limit < 1)) {
            throw InvalidRateLimit::because('A decision counts from zero, its limit and window from one.');
        }
    }

    public static function accepted(?int $limit, int $remaining, int $resetAfter, ?int $window = null): self
    {
        return new self(true, $limit, $remaining, $resetAfter, 0, $window);
    }

    public static function refused(?int $limit, int $remaining, int $resetAfter, int $retryAfter, ?int $window = null): self
    {
        return new self(false, $limit, $remaining, $resetAfter, $retryAfter, $window);
    }

    /**
     * The same decision, made by the limiter of that name.
     */
    public function named(string $limiter): self
    {
        return new self($this->accepted, $this->limit, $this->remaining, $this->resetAfter, $this->retryAfter, $this->window, $limiter);
    }

    /**
     * Whether this decision leaves less than another, so that the response reports the tightest of its limiters; a
     * decision without a limit is never tighter.
     */
    public function isTighterThan(?self $other): bool
    {
        if (null === $this->limit) {
            return false;
        }

        return !$other instanceof self || null === $other->limit || $this->remaining < $other->remaining;
    }
}
