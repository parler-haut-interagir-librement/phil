<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Tests\Support;

use DateTimeImmutable;
use Override;
use Phil\BenefitDeliverables\Contract\ExportJournal;
use Phil\BenefitDeliverables\Export\ExportRecord;

final class InMemoryJournal implements ExportJournal
{
    /** @var array<string, ExportRecord> */
    public array $records = [];

    #[Override]
    public function record(ExportRecord $record): void
    {
        $this->records[$record->id] = $record;
    }

    #[Override]
    public function find(string $id): ?ExportRecord
    {
        return $this->records[$id] ?? null;
    }

    #[Override]
    public function expired(DateTimeImmutable $at, int $limit): array
    {
        $expired = array_filter($this->records, static fn (ExportRecord $record): bool => !$record->fileErasedAt instanceof DateTimeImmutable && $record->expiresAt < $at);

        return \array_slice(array_values($expired), 0, $limit);
    }

    #[Override]
    public function fileErased(string $id, DateTimeImmutable $at): void
    {
        if (isset($this->records[$id])) {
            $this->records[$id] = $this->records[$id]->withFileErasedAt($at);
        }
    }
}
