<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * A row refused: its physical line, its cells as written, and why, cell by cell.
 */
final readonly class RowRejection
{
    /**
     * @param list<string>              $cells
     * @param non-empty-list<CellError> $errors
     */
    public function __construct(
        public int $line,
        public array $cells,
        public array $errors,
    ) {
    }
}
