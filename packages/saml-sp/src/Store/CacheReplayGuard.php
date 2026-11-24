<?php

declare(strict_types=1);

namespace Phil\SamlSp\Store;

use DateTimeImmutable;
use Override;
use Phil\SamlSp\Contract\ReplayGuard;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Remembers the assertions in a PSR-6 cache pool, each until it expires, under the hash of its issuer and its ID. The
 * pool is shared by the workers and the servers of the site, and kept apart from a pool that is cleared on deploy.
 */
final readonly class CacheReplayGuard implements ReplayGuard
{
    public function __construct(private CacheItemPoolInterface $pool)
    {
    }

    #[Override]
    public function firstUse(string $issuer, string $assertionId, DateTimeImmutable $until): bool
    {
        $item = $this->pool->getItem('phil_saml_sp.assertion.'.hash('sha256', \strlen($issuer).':'.$issuer.$assertionId));

        if ($item->isHit()) {
            return false;
        }

        return $this->pool->save($item->set(true)->expiresAt($until));
    }
}
