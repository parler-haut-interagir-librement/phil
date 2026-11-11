<?php

declare(strict_types=1);

namespace Phil\Checkin\Offline;

use Phil\Checkin\ScanId;

/**
 * What became of each scan of an offline journal, in the order the scans were applied: by instant, then by identifier.
 * The device forgets the scans the report names, whatever their status, and keeps the others to send again.
 */
final class SyncReport
{
    /** @var list<array{ScanId, SyncStatus}> */
    private array $statuses = [];

    public function add(ScanId $id, SyncStatus $status): void
    {
        $this->statuses[] = [$id, $status];
    }

    public function count(SyncStatus $status): int
    {
        return \count(array_filter($this->statuses, static fn (array $entry): bool => $status === $entry[1]));
    }

    /**
     * @return list<array{id: string, status: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn (array $entry): array => ['id' => $entry[0]->value, 'status' => $entry[1]->value], $this->statuses);
    }
}
