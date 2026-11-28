<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

/**
 * How a workbook is written: its heading rows, the range of rows of each sheet, and the guard against formulas.
 */
final readonly class WriteOptions
{
    /**
     * @param Headings $headings      the heading row of the sheets that declare columns
     * @param RowRange $range         the rows of data written from each sheet
     * @param bool     $guardFormulas whether a text that a spreadsheet would run as a formula — one starting with `=`,
     *                                `+`, `-`, `@`, a tab or a carriage return — is kept as text; turn it off only for
     *                                texts no person typed
     */
    public function __construct(
        public Headings $headings = Headings::Labels,
        public RowRange $range = new RowRange(),
        public bool $guardFormulas = true,
    ) {
    }
}
