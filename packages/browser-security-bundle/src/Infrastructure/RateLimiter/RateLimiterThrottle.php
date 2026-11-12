<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\RateLimiter;

use Override;
use Phil\BrowserSecurityBundle\Application\Contract\ReportThrottle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The throttle of the report bodies through a limiter of `symfony/rate-limiter`, one per client digest.
 */
final readonly class RateLimiterThrottle implements ReportThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $limiters,
    ) {
    }

    #[Override]
    public function allow(string $client): bool
    {
        return $this->limiters->create($client)->consume()->isAccepted();
    }
}
