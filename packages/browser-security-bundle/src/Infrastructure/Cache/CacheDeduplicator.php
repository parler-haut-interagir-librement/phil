<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Cache;

use Override;
use Phil\BrowserSecurityBundle\Application\Contract\ReportDeduplicator;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The fingerprints announced within a window, in a PSR-6 pool shared by every worker of the application.
 */
final readonly class CacheDeduplicator implements ReportDeduplicator
{
    public const int DEFAULT_WINDOW = 3600;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private int $window = self::DEFAULT_WINDOW,
    ) {
    }

    #[Override]
    public function isNew(string $fingerprint): bool
    {
        $item = $this->pool->getItem('phil_browser_security.'.$fingerprint);

        if ($item->isHit()) {
            return false;
        }

        $this->pool->save($item->set($fingerprint)->expiresAfter($this->window));

        return true;
    }
}
