<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Exception;

use InvalidArgumentException;

/**
 * An argument the library refuses before it writes or reads anything: a sheet name, a cell value, a limit, a dialect or
 * a format it does not know.
 */
final class InvalidSpreadsheet extends InvalidArgumentException implements SpreadsheetException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
