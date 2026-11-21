<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Infrastructure\Cache;

use Override;
use Phil\IdentityProviderGoogle\Application\Contract\SubjectHolds;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The held subjects in a PSR-6 pool, without expiry, under a hash of the provider and the subject. The pool must keep
 * what it is given — a Doctrine DBAL, PDO or Redis pool without eviction, not an in-memory or filesystem cache cleared
 * on deploy — or a held subject is let in again.
 */
final readonly class CacheSubjectHolds implements SubjectHolds
{
    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    #[Override]
    public function hold(string $provider, string $subject): void
    {
        $this->cache->save($this->cache->getItem($this->key($provider, $subject))->set(true));
    }

    #[Override]
    public function release(string $provider, string $subject): void
    {
        $this->cache->deleteItem($this->key($provider, $subject));
    }

    #[Override]
    public function isHeld(string $provider, string $subject): bool
    {
        return $this->cache->getItem($this->key($provider, $subject))->isHit();
    }

    private function key(string $provider, string $subject): string
    {
        return 'phil_idp_google.hold.'.hash('xxh128', $provider."\0".$subject);
    }
}
