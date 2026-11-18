<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Store;

use Exception;
use Override;
use Phil\IdentityClientBundle\Application\Contract\ReplayStore;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Keeps the claimed identifiers in a cache pool, under a lock since a pool has no atomic "add". A claim the pool did not
 * keep — its `save()` answered `false` — is refused: accepting it would let the same token be accepted again.
 *
 * The lock lives five seconds, for one read and one write of the pool. Before the write, it is renewed and checked: a
 * read that outlasted it, and whose lock another request may have taken meanwhile, refuses the claim rather than write
 * it, as does a lock store that cannot confirm it. A refused claim refuses the token, never accepts it twice.
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
            if ($item->isHit() || !$this->stillHeld($lock)) {
                return false;
            }

            return $this->pool->save($item->set('claimed')->expiresAfter($ttl));
        } finally {
            try {
                $lock->release();
            } catch (Exception) {
                // A lock that cannot be released expires on its own, within seconds.
            }
        }
    }

    private function stillHeld(LockInterface $lock): bool
    {
        try {
            $lock->refresh();

            return $lock->isAcquired();
        } catch (Exception) {
            return false;
        }
    }
}
