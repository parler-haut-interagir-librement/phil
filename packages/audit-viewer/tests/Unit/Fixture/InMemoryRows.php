<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Exception\ChainConflict;

/**
 * The trail in memory.
 */
final class InMemoryRows implements AuditRowRepository
{
    /** @var array<string, array<int, AuditRow>> */
    public array $rows = [];

    #[Override]
    public function first(string $segment): ?AuditRow
    {
        $rows = $this->rows[$segment] ?? [];

        return [] === $rows ? null : $rows[min(array_keys($rows))];
    }

    #[Override]
    public function head(string $segment): ?AuditRow
    {
        $rows = $this->rows[$segment] ?? [];

        return [] === $rows ? null : $rows[max(array_keys($rows))];
    }

    #[Override]
    public function append(AuditRow $row): void
    {
        if (isset($this->rows[$row->segment][$row->sequence])) {
            throw ChainConflict::at($row->segment, $row->sequence);
        }

        $this->rows[$row->segment][$row->sequence] = $row;
    }

    #[Override]
    public function rows(string $segment): iterable
    {
        $rows = $this->rows[$segment] ?? [];
        ksort($rows);

        return array_values($rows);
    }

    #[Override]
    public function rowsAfter(string $segment, int $sequence, int $limit): array
    {
        $rows = array_filter($this->rows[$segment] ?? [], static fn (AuditRow $row): bool => $row->sequence > $sequence);
        ksort($rows);

        return \array_slice(array_values($rows), 0, $limit);
    }

    #[Override]
    public function segments(): array
    {
        $segments = array_keys($this->rows);
        sort($segments);

        return $segments;
    }
}
