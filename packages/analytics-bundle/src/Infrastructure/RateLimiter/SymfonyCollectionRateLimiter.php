<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\RateLimiter;

use Override;
use Phil\AnalyticsBundle\Application\Contract\CollectionRateLimiter;
use Phil\AnalyticsBundle\Domain\ValueObject\ClientDigest;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Throwable;

/**
 * The collection budget on Symfony Rate Limiter: one limiter per site and keyed client digest, from the factory the
 * bundle configures (a sliding window by default).
 *
 * **It fails closed.** Any failure of the limiter or its storage answers `false`: a page view lost is better than a
 * counter without a bound. The key holds the digest, never the address.
 */
final readonly class SymfonyCollectionRateLimiter implements CollectionRateLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $factory,
    ) {
    }

    #[Override]
    public function consume(SiteId $site, ClientDigest $digest): bool
    {
        try {
            // Hashed together: a site reference may hold characters a cache key refuses, and a long one would overflow it.
            return $this->factory->create(hash('xxh128', $site->value."\0".$digest->toString()))->consume()->isAccepted();
        } catch (Throwable) {
            return false;
        }
    }
}
