<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Cache;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\NonceStore;
use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The accepted nonces in a PSR-6 cache pool shared by the site's servers — Redis, a database, APCu on one server.
 *
 * A pool has no atomic insert: the read and the write of a nonce happen under a lock of the Symfony Lock component, so
 * that two requests with the same nonce never both pass; a nonce whose lock another request holds is a replay. The
 * pool and the lock store must be shared by every server of the site. A nonce the pool refuses to save fails the
 * request: it is never accepted unkept.
 */
final readonly class CacheNonceStore implements NonceStore
{
    /** The longest the check and the write of a nonce hold its lock, in seconds. */
    private const float LOCK_TTL = 10.0;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private LockFactory $locks,
    ) {
    }

    #[Override]
    public function remember(string $nonce, int $seconds): bool
    {
        $key = 'phil_sites_console.nonce.'.hash('xxh128', $nonce);
        $lock = $this->locks->createLock($key, self::LOCK_TTL);

        if (!$lock->acquire()) {
            return false;
        }

        try {
            $item = $this->pool->getItem($key);

            if ($item->isHit()) {
                return false;
            }

            if (!$this->pool->save($item->set(true)->expiresAfter($seconds))) {
                throw StateNotKept::nonce();
            }

            return true;
        } finally {
            $lock->release();
        }
    }
}
