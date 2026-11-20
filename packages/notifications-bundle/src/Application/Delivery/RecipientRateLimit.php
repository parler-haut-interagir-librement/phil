<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * How many notifications each recipient gets outside the centre, by a limiter of `symfony/rate-limiter` named in the
 * configuration — a sliding window, such as five an hour —, one token per delivery. An `urgent` type passes whatever
 * the count; the others wait in the centre once the limit is reached. The limiter's storage must be shared by every
 * server.
 */
final readonly class RecipientRateLimit
{
    public const string PREFIX = 'phil_notifications.';

    public function __construct(
        private RateLimiterFactoryInterface $limiter,
    ) {
    }

    /**
     * Spends one token of the recipient, and says whether one was left.
     */
    public function consume(string $recipient): bool
    {
        return $this->limiter->create(self::PREFIX.$recipient)->consume()->isAccepted();
    }
}
