<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Contract;

use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;

/**
 * Writes a sheet in one format to a stream: `php://output` for a response, a file, or `php://temp`. A writer keeps
 * nothing between two sheets.
 */
interface ExportWriter
{
    public function format(): Format;

    /**
     * @param resource $stream open for writing
     *
     * @throws ExportRefused when the sheet goes past a limit of the format, holds a cell of no kind it writes, or the
     *                       stream cannot be written
     */
    public function write(Sheet $sheet, mixed $stream): void;
}
