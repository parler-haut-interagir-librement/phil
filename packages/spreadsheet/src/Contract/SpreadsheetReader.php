<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Contract;

use Generator;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Row;

/**
 * Reads the rows of a file in one format, one row at a time, within the limits it was given. An implementation keeps
 * no state between two calls.
 */
interface SpreadsheetReader
{
    public function format(): Format;

    /**
     * The names of the file's sheets, in order.
     *
     * @return list<string>
     *
     * @throws ReadRefused when the file cannot be read, goes over a limit or is unsafe
     */
    public function sheetNames(string $path): array;

    /**
     * The rows of a sheet that hold at least one cell, in order. The file is read as the rows are taken: a limit
     * reached half way throws then.
     *
     * @param string|null $sheet the sheet's name, the first sheet when null
     *
     * @return Generator<int, Row>
     *
     * @throws ReadRefused when the file cannot be read, goes over a limit, is unsafe, or has no such sheet
     */
    public function rows(string $path, ?string $sheet = null): Generator;
}
