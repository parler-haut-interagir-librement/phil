<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * A pool that reads like any other and saves nothing: a full Redis, a database gone read-only.
 */
final class RefusingPool extends ArrayAdapter
{
    #[Override]
    public function save(CacheItemInterface $item): bool
    {
        return false;
    }
}
