<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Import;

use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\RowAction;

/**
 * Rows written together, in order: never a row and a row of the file it refers to, so that the record referred to is
 * written, and known to be accepted, before the batch that needs it.
 */
final readonly class Batch
{
    /**
     * @param int                        $number from 1
     * @param non-empty-list<PlannedRow> $rows
     */
    public function __construct(
        public string $type,
        public int $number,
        public array $rows,
    ) {
    }

    /**
     * @return list<PlannedRow>
     */
    public function creates(): array
    {
        return array_values(array_filter($this->rows, static fn (PlannedRow $row): bool => RowAction::Create === $row->action));
    }

    /**
     * @return list<PlannedRow>
     */
    public function updates(): array
    {
        return array_values(array_filter($this->rows, static fn (PlannedRow $row): bool => RowAction::Update === $row->action));
    }
}
