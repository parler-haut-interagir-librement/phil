<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * The bounds of a read, for files nobody checked: a file past any of them is refused, never read in part.
 */
final readonly class ReadLimits
{
    /**
     * @param int $maxFileBytes          the most bytes of the file itself, CSV file or archive
     * @param int $maxEntries            the most entries of an archive
     * @param int $maxPartBytes          the most bytes of one part of an archive once uncompressed, counted as it is read
     * @param int $maxSharedStringsBytes the most memory the shared texts of an XLSX workbook may take, in bytes
     * @param int $maxRows               the highest row number with a cell
     * @param int $maxColumns            the most columns of a row
     * @param int $maxCells              the most cells given by one read, empty cells inside the rows included
     *
     * @throws InvalidSpreadsheet when a limit is below 1
     */
    public function __construct(
        public int $maxFileBytes = 256 * 1024 * 1024,
        public int $maxEntries = 1_000,
        public int $maxPartBytes = 1024 * 1024 * 1024,
        public int $maxSharedStringsBytes = 128 * 1024 * 1024,
        public int $maxRows = 1_048_576,
        public int $maxColumns = 16_384,
        public int $maxCells = 50_000_000,
    ) {
        foreach (get_object_vars($this) as $name => $value) {
            if ($value < 1) {
                throw InvalidSpreadsheet::because(\sprintf('The read limit %s is 1 or more.', $name));
            }
        }
    }
}
