<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\RateLimiter;

use LogicException;
use Override;
use Symfony\Component\RateLimiter\Exception\ReserveNotSupportedException;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Reservation;

/**
 * The limiter of one key that `AttributeLimiterFactory` creates for Symfony's `#[RateLimit]`: it consumes through
 * `AttributeLimits`, and does nothing else. A reservation or a reset goes to the limiter's own factory or storage.
 */
final readonly class AttributeLimiter implements LimiterInterface
{
    public function __construct(
        private AttributeLimits $limits,
        private string $limiter,
        private ?string $key,
    ) {
    }

    /**
     * @throws ReserveNotSupportedException always: `#[RateLimit]` only consumes
     */
    #[Override]
    public function reserve(int $tokens = 1, ?float $maxTime = null): Reservation
    {
        throw new ReserveNotSupportedException(self::class);
    }

    #[Override]
    public function consume(int $tokens = 1): RateLimit
    {
        return $this->limits->consume($this->limiter, $this->key, $tokens);
    }

    /**
     * @throws LogicException always: the state lives in the limiter's storage, under a key this limiter never sees
     */
    #[Override]
    public function reset(): void
    {
        throw new LogicException(\sprintf('The rate limiter "%s" of #[RateLimit] is reset through its own storage.', $this->limiter));
    }
}
