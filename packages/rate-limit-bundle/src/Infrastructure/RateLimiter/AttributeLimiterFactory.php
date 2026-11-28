<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\RateLimiter;

use Override;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A limiter of this bundle as Symfony's `#[RateLimit]` names it: the bundle tags one for each of its limiters
 * `rate_limiter` with the limiter's name, beside those of the framework's `rate_limiter` configuration.
 *
 * ```php
 * use Symfony\Component\HttpKernel\Attribute\RateLimit;
 *
 * #[RateLimit('api')]
 * #[RateLimit('exports', key: 'user', tokens: 5)]
 * public function export(): Response
 * ```
 */
final readonly class AttributeLimiterFactory implements RateLimiterFactoryInterface
{
    public function __construct(
        private AttributeLimits $limits,
        /** The name of the limiter of this bundle. */
        private string $limiter,
    ) {
    }

    #[Override]
    public function create(?string $key = null): LimiterInterface
    {
        return new AttributeLimiter($this->limits, $this->limiter, $key);
    }
}
