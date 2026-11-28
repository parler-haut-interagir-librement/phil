<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Cache;

use Override;
use Phil\SitesConsoleBundle\Application\Contract\ConsoleKeyStore;
use Phil\SitesConsoleBundle\Application\Exception\StateNotKept;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The console keys a rotation gave the site, in a PSR-6 cache pool shared by the site's servers that survives
 * deploys — Redis or a database, not a pool emptied by `cache:clear`. The entry never expires. A state the pool
 * refuses to save fails the rotation.
 */
final readonly class CacheConsoleKeyStore implements ConsoleKeyStore
{
    public const string ITEM = 'phil_sites_console.console_keys';

    public function __construct(
        private CacheItemPoolInterface $pool,
    ) {
    }

    #[Override]
    public function read(): ?string
    {
        $item = $this->pool->getItem(self::ITEM);
        $value = $item->get();

        return $item->isHit() && \is_string($value) ? $value : null;
    }

    #[Override]
    public function write(string $state): void
    {
        if (!$this->pool->save($this->pool->getItem(self::ITEM)->set($state))) {
            throw StateNotKept::consoleKeys();
        }
    }
}
