<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\RateLimiter;

use Override;
use Phil\ErrorTracking\Contract\CaptureThrottle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A throttle through a limiter of `symfony/rate-limiter`, one per key: a fingerprint for the events of a group, a
 * keyed digest of the client for the browsers' reports.
 */
final readonly class RateLimiterThrottle implements CaptureThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $limiters,
    ) {
    }

    #[Override]
    public function allow(string $key): bool
    {
        return $this->limiters->create($key)->consume()->isAccepted();
    }
}
