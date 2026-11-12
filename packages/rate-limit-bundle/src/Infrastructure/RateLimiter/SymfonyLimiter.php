<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\RateLimiter;

use DateTimeImmutable;
use Override;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A limiter of `symfony/rate-limiter`: a fixed window, a sliding window, a token bucket, a compound of them, or any
 * factory the application declares. Each consumption creates the limiter of the key from the factory, whose state
 * lives in its storage, never in this service.
 *
 * A refusal waits until the moment the limiter gives for the next hit, rounded up to the second. The reset is the
 * moment the limiter is back to its full capacity; when its policy gives none, the window's length for an accepted
 * request — an upper bound — and the wait for a refused one. A factory
 * without a limit, the `no_limit` policy, writes no header.
 */
final readonly class SymfonyLimiter implements Limiter
{
    public function __construct(
        private RateLimiterFactoryInterface $factory,
        /** The length in seconds of the window, for the headers; null when the policy has none. */
        private ?int $window = null,
        /** The clock the seconds are counted from; the system's by default, which the limiters read too. */
        private ?ClockInterface $clock = null,
    ) {
    }

    #[Override]
    public function consume(string $key, int $amount, Request $request): Decision
    {
        $limit = $this->factory->create($key)->consume($amount);
        $now = $this->clock instanceof ClockInterface ? (float) $this->clock->now()->format('U.u') : microtime(true);
        $capacity = \PHP_INT_MAX === $limit->getLimit() ? null : $limit->getLimit();
        $resetAt = $limit->getResetAt();
        $retryAfter = $limit->isAccepted() ? 0 : $this->secondsFrom($now, $limit->getRetryAfter());
        $resetAfter = $resetAt instanceof DateTimeImmutable ? $this->secondsFrom($now, $resetAt) : ($limit->isAccepted() ? $this->window ?? 0 : $retryAfter);

        return new Decision($limit->isAccepted(), $capacity, $limit->getRemainingTokens(), $resetAfter, $retryAfter, $this->window);
    }

    /**
     * The whole seconds from now until that moment, rounded up, never below zero.
     */
    private function secondsFrom(float $now, DateTimeImmutable $moment): int
    {
        return max(0, (int) ceil((float) $moment->format('U.u') - $now));
    }
}
