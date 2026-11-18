<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;

/**
 * The trail of phil/audit-bundle in memory.
 */
final class AuditRows implements AuditRowRepository
{
    /** @var list<AuditRow> */
    public array $rows = [];

    #[Override]
    public function first(string $segment): ?AuditRow
    {
        return array_find($this->rows, static fn (AuditRow $row): bool => $row->segment === $segment);
    }

    #[Override]
    public function head(string $segment): ?AuditRow
    {
        return array_find(array_reverse($this->rows), static fn (AuditRow $row): bool => $row->segment === $segment);
    }

    #[Override]
    public function append(AuditRow $row): void
    {
        $this->rows[] = $row;
    }

    #[Override]
    public function rows(string $segment): iterable
    {
        return array_values(array_filter($this->rows, static fn (AuditRow $row): bool => $row->segment === $segment));
    }

    #[Override]
    public function rowsAfter(string $segment, int $sequence, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->rows, static fn (AuditRow $row): bool => $row->segment === $segment && $row->sequence > $sequence)), 0, $limit);
    }

    #[Override]
    public function segments(): array
    {
        return array_values(array_unique(array_map(static fn (AuditRow $row): string => $row->segment, $this->rows)));
    }
}
