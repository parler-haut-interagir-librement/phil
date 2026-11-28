<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Contract;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;

/**
 * Writes a workbook in one format to a stream, reading the rows of each sheet once, in order. An implementation keeps
 * no state between two calls: one instance writes any number of files, one after the other or at once.
 */
interface SpreadsheetWriter
{
    public function format(): Format;

    /**
     * @param resource $stream an open stream the file is written to; it is left open
     *
     * @throws InvalidSpreadsheet when the stream is not one, a row is not an array or a value has no cell type
     * @throws WriteFailed        when the format cannot hold the workbook, or the stream refuses the bytes
     */
    public function write(Workbook $workbook, mixed $stream, WriteOptions $options = new WriteOptions()): void;
}
