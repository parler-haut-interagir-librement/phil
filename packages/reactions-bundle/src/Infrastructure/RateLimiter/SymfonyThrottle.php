<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Infrastructure\RateLimiter;

use Override;
use Phil\ReactionsBundle\Application\Contract\ReactionThrottle;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * A limiter of symfony/rate-limiter, per member.
 */
final readonly class SymfonyThrottle implements ReactionThrottle
{
    public function __construct(
        private RateLimiterFactoryInterface $limiters,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function consume(string $member): void
    {
        $limit = $this->limiters->create($member)->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyReactions(max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp()));
        }
    }
}
