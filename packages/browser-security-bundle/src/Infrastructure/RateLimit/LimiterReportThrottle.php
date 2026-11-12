<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\RateLimit;

use Override;
use Phil\BrowserSecurityBundle\Application\Contract\ReportThrottle;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The throttle of the report bodies through a limiter of phil/rate-limit-bundle. The key is the keyed digest the
 * receiver computes, never the address.
 */
final readonly class LimiterReportThrottle implements ReportThrottle
{
    public function __construct(
        private Limiter $limiter,
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function allow(string $client): bool
    {
        return $this->limiter->consume($client, 1, $this->requests->getCurrentRequest() ?? new Request())->accepted;
    }
}
