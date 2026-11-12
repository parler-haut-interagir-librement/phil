<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Infrastructure\Salt;

use Override;
use Phil\IpAnonymizer\Contract\ExpiringSaltStore;
use Psr\Cache\CacheItemPoolInterface;
use SensitiveParameter;
use Symfony\Component\Lock\LockFactory;

/**
 * The salts of the client digest in a cache pool, each for the rest of its day, then forgotten by the pool's expiry.
 *
 * **Shared and not persisted.** Every server of collection must reach the same pool, or each draws its own salt and the
 * limiter's budget is multiplied by the number of servers; and the pool must not outlive its entries — no snapshot, no
 * backup —, or the salts it was meant to forget link the digests of two days again. A Redis or Memcached pool without
 * persistence fits both. A lock factory whose store the servers share makes the first claim of a day atomic between
 * them; without one, two servers starting a day at the same instant may each keep their own salt for that day.
 */
final readonly class CacheSaltStore implements ExpiringSaltStore
{
    private const string PREFIX = 'phil_analytics.salt.';

    public function __construct(
        private CacheItemPoolInterface $pool,
        private ?LockFactory $locks = null,
    ) {
    }

    #[Override]
    public function claim(string $saltId, #[SensitiveParameter] string $candidate, int $ttlSeconds): string
    {
        // A cache key accepts a limited alphabet; the identifier of a salt is digits and a dash, hashed all the same.
        $key = self::PREFIX.hash('xxh128', $saltId);
        $lock = $this->locks?->createLock($key, 10.0);
        $lock?->acquire(true);

        try {
            $item = $this->pool->getItem($key);
            $stored = $item->get();

            if ($item->isHit() && \is_string($stored) && '' !== $stored) {
                return $stored;
            }

            $this->pool->save($item->set($candidate)->expiresAfter($ttlSeconds));

            return $candidate;
        } finally {
            $lock?->release();
        }
    }
}
