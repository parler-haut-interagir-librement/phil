<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Contract;

use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Plan\CellError;

/**
 * The writer of one importable type, implemented by the application or the bundle that owns its records: the library
 * never writes to a database itself. It also answers the planner's lookups.
 */
interface ImportTarget extends KeyLookup
{
    /**
     * The importable type it writes, the type of the profile.
     */
    public function type(): string;

    /**
     * Creates and updates the rows of a batch, in their order. A batch is written whole or the target says, by line,
     * which rows it refused and why; the reasons never repeat a value of the row.
     *
     * @return array<int, non-empty-list<CellError>> the errors of the rows refused, by line
     */
    public function write(Batch $batch): array;
}
