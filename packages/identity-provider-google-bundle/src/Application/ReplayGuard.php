<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Application;

use Psr\Cache\CacheItemPoolInterface;

/**
 * The identifiers of the security event tokens already acted on, kept as long as a token is accepted: a token sent
 * again — a retry of the transmitter, or a replay — is acknowledged without being acted on twice. A token is remembered
 * once it was acted on, so that a failure halfway lets the transmitter's retry finish the work. Only a hash of the
 * issuer and the identifier is kept.
 */
final readonly class ReplayGuard
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private int $lifetime = 604_800,
    ) {
    }

    public function isKnown(string $issuer, string $id): bool
    {
        return $this->cache->getItem($this->key($issuer, $id))->isHit();
    }

    public function remember(string $issuer, string $id): void
    {
        $this->cache->save($this->cache->getItem($this->key($issuer, $id))->set(true)->expiresAfter($this->lifetime));
    }

    private function key(string $issuer, string $id): string
    {
        return 'phil_idp_google.set.'.hash('xxh128', $issuer."\0".$id);
    }
}
