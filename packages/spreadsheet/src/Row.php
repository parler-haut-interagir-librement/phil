<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use DateTimeImmutable;

/**
 * A row read from a file: its number in the sheet, from 1, and its cells, without the empty cells at its end. A row
 * with no cell left is never given.
 */
final readonly class Row
{
    /**
     * @param int        $number the row's number in its sheet, as the spreadsheet shows it; the record's for CSV
     * @param list<Cell> $cells
     */
    public function __construct(
        public int $number,
        public array $cells,
    ) {
    }

    /**
     * The cell at a column, from 0; an empty cell past the end of the row.
     */
    public function cell(int $index): Cell
    {
        return $this->cells[$index] ?? Cell::empty();
    }

    /**
     * The values of the cells, null for an empty one.
     *
     * @return list<string|int|float|bool|DateTimeImmutable|null>
     */
    public function values(): array
    {
        return array_map(static fn (Cell $cell): string|int|float|bool|DateTimeImmutable|null => $cell->value, $this->cells);
    }
}
