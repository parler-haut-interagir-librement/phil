<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Store;

use Override;
use Phil\IdentityClientBundle\Application\Contract\ReplayFloor;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;

/**
 * Keeps the floor in the pool of the replay store, as its sentinel: one entry without expiry, written with the store.
 *
 * A pool that no longer holds the entry was flushed, evicted or replaced, and lost the logout tokens and ended sessions
 * it remembered with it: the floor becomes now, and is written again. A configured floor, such as the instant of a key
 * rollover at the provider, raises it once it is reached: one set in the future is not in force yet.
 *
 * The pool has to be shared by every server of the application, and must not evict.
 */
final readonly class CacheReplayFloor implements ReplayFloor
{
    public const string KEY = 'phil_identity_client.replay_floor';

    public function __construct(
        private CacheItemPoolInterface $pool,
        private ClockInterface $clock,
        private ?int $notBefore = null,
    ) {
    }

    #[Override]
    public function floor(): int
    {
        $now = $this->clock->now()->getTimestamp();
        $item = $this->pool->getItem(self::KEY);
        $recorded = $item->isHit() ? $item->get() : null;
        if (!\is_int($recorded)) {
            $recorded = $now;
            $this->pool->save($item->set($recorded));
        }

        return null !== $this->notBefore && $this->notBefore <= $now ? max($recorded, $this->notBefore) : $recorded;
    }
}
