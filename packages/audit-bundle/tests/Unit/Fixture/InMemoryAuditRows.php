<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Exception\ChainConflict;

/**
 * The trail in memory; `races` rows are appended by "another process" before each of the next appends, and `scans`
 * counts the full reads of a segment.
 */
final class InMemoryAuditRows implements AuditRowRepository
{
    /** @var array<string, array<int, AuditRow>> */
    public array $rows = [];

    public int $races = 0;

    /** @var list<int> the limit of each read of rows after a sequence */
    public array $limits = [];

    /** How many times every row of a segment was read. */
    public int $scans = 0;

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
        if ($this->races > 0) {
            --$this->races;
            $this->rows[$row->segment][$row->sequence] = $row;

            throw ChainConflict::at($row->segment, $row->sequence);
        }

        if (isset($this->rows[$row->segment][$row->sequence])) {
            throw ChainConflict::at($row->segment, $row->sequence);
        }

        $this->rows[$row->segment][$row->sequence] = $row;
    }

    #[Override]
    public function rows(string $segment): iterable
    {
        ++$this->scans;
        $rows = $this->rows[$segment] ?? [];
        ksort($rows);

        return array_values($rows);
    }

    #[Override]
    public function rowsAfter(string $segment, int $sequence, int $limit): array
    {
        $this->limits[] = $limit;
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
