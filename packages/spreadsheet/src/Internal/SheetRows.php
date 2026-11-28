<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Generator;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\WriteOptions;

/**
 * The rows a writer writes for a sheet: its heading row, then its rows of data within the range, as cells.
 *
 * @internal
 */
final class SheetRows
{
    /**
     * The heading row, or null when the options ask for none or the sheet has no column.
     *
     * @return list<Cell>|null
     */
    public static function headings(Sheet $sheet, WriteOptions $options): ?array
    {
        if (Headings::None === $options->headings || [] === $sheet->columns) {
            return null;
        }

        return array_map(static fn (Column $column): Cell => Cell::text($column->heading($options->headings)), $sheet->columns);
    }

    /**
     * @return Generator<int, list<Cell>>
     *
     * @throws InvalidSpreadsheet when a row holds a value with no cell type
     */
    public static function data(Sheet $sheet, WriteOptions $options): Generator
    {
        $first = $options->range->first;
        $last = $options->range->last;
        $index = 0;

        foreach ($sheet->rows as $row) {
            ++$index;

            if ($index < $first) {
                continue;
            }

            $cells = [];

            foreach ($row as $value) {
                $cells[] = Cell::from($value);
            }

            yield $cells;

            if ($index === $last) {
                return;
            }
        }
    }
}
