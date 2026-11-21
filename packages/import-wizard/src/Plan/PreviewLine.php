<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * One line of the dry run, in the order of the file: what the import will do with it and, for a refused row, why.
 */
final readonly class PreviewLine
{
    /**
     * @param list<CellError> $errors empty unless the row is refused
     */
    public function __construct(
        public int $line,
        public RowAction $action,
        public array $errors = [],
    ) {
    }
}
