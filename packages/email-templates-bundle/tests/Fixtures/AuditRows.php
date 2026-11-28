<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Fixtures;

use Override;
use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\AuditBundle\Domain\Contract\AuditRowRepository;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

/**
 * The rows of an audit trail in memory, serialized as JSON: what the audit of the templates writes to.
 */
final class AuditRows implements AuditRowRepository, CanonicalSerializer
{
    /** @var array<string, list<AuditRow>> */
    public array $rows = [];

    #[Override]
    public function first(string $segment): ?AuditRow
    {
        return $this->rows[$segment][0] ?? null;
    }

    #[Override]
    public function head(string $segment): ?AuditRow
    {
        $rows = $this->rows[$segment] ?? [];

        return [] === $rows ? null : $rows[\count($rows) - 1];
    }

    #[Override]
    public function append(AuditRow $row): void
    {
        $this->rows[$row->segment][] = $row;
    }

    #[Override]
    public function rows(string $segment): iterable
    {
        return $this->rows[$segment] ?? [];
    }

    #[Override]
    public function rowsAfter(string $segment, int $sequence, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->rows[$segment] ?? [], static fn (AuditRow $row): bool => $row->sequence > $sequence)), 0, $limit);
    }

    #[Override]
    public function segments(): array
    {
        return array_keys($this->rows);
    }

    #[Override]
    public function version(): string
    {
        return 'json-1';
    }

    #[Override]
    public function serialize(array $fields): string
    {
        return json_encode($fields, \JSON_THROW_ON_ERROR);
    }
}
