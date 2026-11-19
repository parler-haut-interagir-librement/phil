<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\RateLimiter;

use Override;
use Phil\ShortLinkBundle\Application\Contract\CreationThrottle;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The allowance of creations on a Symfony rate limiter, one window per author. The author is hashed before it names
 * the window, so that the store of the limiter keeps no account name nor address.
 */
final readonly class RateLimiterCreationThrottle implements CreationThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $factory,
    ) {
    }

    #[Override]
    public function consume(string $author): bool
    {
        return $this->factory->create(hash('sha256', 'phil_short_link'.$author))->consume()->isAccepted();
    }
}
