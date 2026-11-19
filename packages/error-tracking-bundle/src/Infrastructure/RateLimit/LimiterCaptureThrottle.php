<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Infrastructure\RateLimit;

use Override;
use Phil\ErrorTracking\Contract\CaptureThrottle;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The throttle of the browsers' reports through a limiter of phil/rate-limit-bundle. The key is the keyed digest the
 * receiver computes, never the address.
 */
final readonly class LimiterCaptureThrottle implements CaptureThrottle
{
    public function __construct(
        private Limiter $limiter,
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function allow(string $key): bool
    {
        return $this->limiter->consume($key, 1, $this->requests->getCurrentRequest() ?? new Request())->accepted;
    }
}
