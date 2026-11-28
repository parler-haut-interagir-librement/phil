<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * The rows of data to write, counted from 1 without the heading row: from the first to the last, both included. The
 * rows after the last are never read from their iterable.
 */
final readonly class RowRange
{
    /**
     * @param int      $first the first row written, from 1
     * @param int|null $last  the last row written, every row up to the end when null
     *
     * @throws InvalidSpreadsheet when the first row is below 1 or the last before the first
     */
    public function __construct(
        public int $first = 1,
        public ?int $last = null,
    ) {
        if ($first < 1) {
            throw InvalidSpreadsheet::because('The first row of a range is 1 or more.');
        }

        if (null !== $last && $last < $first) {
            throw InvalidSpreadsheet::because('The last row of a range comes at or after its first.');
        }
    }
}
