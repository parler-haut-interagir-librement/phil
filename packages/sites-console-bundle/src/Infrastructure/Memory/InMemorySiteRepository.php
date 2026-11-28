<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use Override;
use Phil\SitesConsoleBundle\Domain\Contract\SiteRepository;
use Phil\SitesConsoleBundle\Domain\Model\Site;

/**
 * The sites in memory, for tests and demonstrations.
 */
final class InMemorySiteRepository implements SiteRepository
{
    /** @var array<string, Site> */
    private array $sites = [];

    #[Override]
    public function get(string $id): ?Site
    {
        return $this->sites[$id] ?? null;
    }

    #[Override]
    public function all(): array
    {
        $sites = $this->sites;
        ksort($sites, \SORT_STRING);

        return array_values($sites);
    }

    #[Override]
    public function save(Site $site): void
    {
        $this->sites[$site->id()] = $site;
    }

    #[Override]
    public function remove(Site $site): void
    {
        unset($this->sites[$site->id()]);
    }
}
