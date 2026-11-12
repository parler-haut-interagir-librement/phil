<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Store;

use Override;
use Phil\IdentityClientBundle\Application\Contract\ReplayStore;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Keeps the claimed identifiers in a cache pool, under a lock since a pool has no atomic "add".
 *
 * Both have to be shared by every server of the application.
 */
final readonly class CacheReplayStore implements ReplayStore
{
    private const float LOCK_TTL = 5.0;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private LockFactory $locks,
    ) {
    }

    #[Override]
    public function claim(string $tokenId, int $ttl): bool
    {
        $key = 'phil_identity_client.jti.'.hash('sha256', $tokenId);
        $lock = $this->locks->createLock($key, self::LOCK_TTL, false);
        if (!$lock->acquire(false)) {
            return false;
        }

        try {
            $item = $this->pool->getItem($key);
            if ($item->isHit()) {
                return false;
            }

            $this->pool->save($item->set('claimed')->expiresAfter($ttl));

            return true;
        } finally {
            $lock->release();
        }
    }
}
