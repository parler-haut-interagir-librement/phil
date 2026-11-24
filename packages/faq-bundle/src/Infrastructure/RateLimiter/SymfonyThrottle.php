<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\RateLimiter;

use Override;
use Phil\FaqBundle\Application\Contract\Throttle;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A limiter of symfony/rate-limiter, per question or per language.
 */
final readonly class SymfonyThrottle implements Throttle
{
    public function __construct(
        private RateLimiterFactoryInterface $limiters,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function consume(string $key): void
    {
        $limit = $this->limiters->create($key)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyRequests(max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp()));
        }
    }
}
