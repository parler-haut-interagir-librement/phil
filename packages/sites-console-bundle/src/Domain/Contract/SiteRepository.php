<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use Phil\SitesConsoleBundle\Domain\Model\Site;

/**
 * The sites the console operates.
 */
interface SiteRepository
{
    public function get(string $id): ?Site;

    /**
     * @return list<Site> sorted by identifier
     */
    public function all(): array;

    public function save(Site $site): void;

    public function remove(Site $site): void;
}
