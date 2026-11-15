<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\RateLimiter;

use Override;
use Phil\NewsletterBundle\Application\Contract\SendThrottle;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\StorageInterface;

/**
 * The pace of sending on Symfony's rate limiter: a token bucket per profile and recipient domain, refilled at the
 * profile's rate for that domain each minute, and a fixed window of a day per profile for its ceiling. The state lives
 * in the limiter's storage — a cache pool shared by the workers —, nothing in the service.
 */
final readonly class RateLimiterSendThrottle implements SendThrottle
{
    public function __construct(
        private StorageInterface $storage,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function wait(SenderProfile $profile, string $domain): int
    {
        $domain = strtolower($domain);
        $rate = $profile->rateFor($domain);
        $pace = new RateLimiterFactory(['id' => 'phil_newsletter_pace', 'policy' => 'token_bucket', 'limit' => $rate, 'rate' => ['interval' => '1 minute', 'amount' => $rate]], $this->storage)
            ->create($profile->name.'|'.$domain)
            ->consume();

        if (!$pace->isAccepted()) {
            return $this->seconds($pace->getRetryAfter()->getTimestamp());
        }

        if (null === $profile->dailyCap) {
            return 0;
        }

        $cap = new RateLimiterFactory(['id' => 'phil_newsletter_cap', 'policy' => 'fixed_window', 'limit' => $profile->dailyCap, 'interval' => '1 day'], $this->storage)
            ->create($profile->name)
            ->consume();

        return $cap->isAccepted() ? 0 : $this->seconds($cap->getRetryAfter()->getTimestamp());
    }

    private function seconds(int $until): int
    {
        return max(1, $until - $this->clock->now()->getTimestamp());
    }
}
