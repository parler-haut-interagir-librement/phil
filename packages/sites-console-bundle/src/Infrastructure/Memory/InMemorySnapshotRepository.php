<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;

/**
 * The snapshots in memory, for tests and demonstrations.
 */
final class InMemorySnapshotRepository implements SnapshotRepository
{
    /** @var array<string, Snapshot> by identifier */
    private array $snapshots = [];

    #[Override]
    public function save(Snapshot $snapshot): void
    {
        $this->snapshots[$snapshot->id()] = $snapshot;
    }

    #[Override]
    public function latest(string $siteId): ?Snapshot
    {
        return $this->history($siteId, 1)[0] ?? null;
    }

    #[Override]
    public function history(string $siteId, int $limit): array
    {
        $snapshots = array_filter($this->snapshots, static fn (Snapshot $snapshot): bool => $snapshot->siteId() === $siteId);
        krsort($snapshots, \SORT_STRING);

        return \array_slice(array_values($snapshots), 0, max(1, $limit));
    }

    #[Override]
    public function purgeBefore(DateTimeImmutable $moment): int
    {
        $removed = 0;

        foreach ($this->snapshots as $id => $snapshot) {
            if ($snapshot->takenAt() < $moment && $this->latest($snapshot->siteId())?->id() !== $id) {
                unset($this->snapshots[$id]);
                ++$removed;
            }
        }

        return $removed;
    }

    #[Override]
    public function removeSite(string $siteId): void
    {
        $this->snapshots = array_filter($this->snapshots, static fn (Snapshot $snapshot): bool => $snapshot->siteId() !== $siteId);
    }
}
