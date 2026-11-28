<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;

/**
 * Makes the rows of one read: the gaps between cells filled with empty cells, the empty cells at the end dropped, an
 * empty row dropped, and the limits of rows, columns and cells held across the read.
 *
 * @internal
 */
final class RowBuilder
{
    private int $cells = 0;

    public function __construct(private readonly ReadLimits $limits)
    {
    }

    /**
     * @param array<int, Cell> $cells the cells by column, from 0, in any order, with gaps
     *
     * @throws ReadRefused when the row is past the limit of rows, a cell past that of columns, or the read past that of
     *                     cells
     */
    public function row(int $number, array $cells): ?Row
    {
        $last = -1;

        foreach ($cells as $column => $cell) {
            if (!$cell->isEmpty() && $column > $last) {
                $last = $column;
            }
        }

        if (-1 === $last) {
            return null;
        }

        if ($number > $this->limits->maxRows) {
            throw ReadRefused::overLimit('The number of the row '.$number, $this->limits->maxRows);
        }

        if ($last >= $this->limits->maxColumns) {
            throw ReadRefused::overLimit(\sprintf('The number of columns of the row %d', $number), $this->limits->maxColumns);
        }

        $this->cells += $last + 1;

        if ($this->cells > $this->limits->maxCells) {
            throw ReadRefused::overLimit('The number of cells read', $this->limits->maxCells);
        }

        $row = [];

        for ($column = 0; $column <= $last; ++$column) {
            $row[] = $cells[$column] ?? Cell::empty();
        }

        return new Row($number, $row);
    }
}
