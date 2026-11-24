<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Cache;

use DateTimeImmutable;
use Override;
use Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The bounce history in a cache pool of the application: a claim is an item that expires with it, the soft bounces of
 * a subject one item that holds their times. Every key is a hash: no address reaches the pool.
 *
 * The pool must keep its items until they expire — a filesystem, Redis or database pool, not `cache.adapter.array` —
 * and be shared by the web servers and the workers that receive bounces.
 */
final readonly class CacheBounceHistoryRepository implements BounceHistoryRepository
{
    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function claim(string $key, DateTimeImmutable $until): bool
    {
        $item = $this->pool->getItem('phil_email_address.claim.'.hash('xxh128', $key));

        if ($item->isHit()) {
            return false;
        }

        $this->pool->save($item->set($until->getTimestamp())->expiresAt($until));

        return true;
    }

    #[Override]
    public function recordSoftBounce(string $subject, DateTimeImmutable $at, DateTimeImmutable $since): int
    {
        $item = $this->pool->getItem($this->softKey($subject));
        $stored = $item->isHit() ? $item->get() : [];
        $start = $since->getTimestamp();
        $times = array_values(array_filter(\is_array($stored) ? $stored : [], static fn (mixed $time): bool => \is_int($time) && $time >= $start));
        $times[] = $at->getTimestamp();
        $this->pool->save($item->set($times)->expiresAt($at->add($at->diff($since, true))));

        return \count($times);
    }

    #[Override]
    public function forgetSoftBounces(string $subject): void
    {
        $this->pool->deleteItem($this->softKey($subject));
    }

    private function softKey(string $subject): string
    {
        return 'phil_email_address.soft.'.hash('xxh128', $subject);
    }
}
