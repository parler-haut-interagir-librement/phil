<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;

/**
 * What the console read of each site, over time.
 */
interface SnapshotRepository
{
    public function save(Snapshot $snapshot): void;

    public function latest(string $siteId): ?Snapshot;

    /**
     * @return list<Snapshot> the newest first
     */
    public function history(string $siteId, int $limit): array;

    /**
     * Removes the snapshots taken before that moment, except the latest of each site.
     *
     * @return int the snapshots removed
     */
    public function purgeBefore(DateTimeImmutable $moment): int;

    /**
     * Removes every snapshot of a site.
     */
    public function removeSite(string $siteId): void;
}
