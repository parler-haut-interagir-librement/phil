<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Exception;

use RuntimeException;

/**
 * A file that cannot be written: a value the format cannot hold, a limit of the format reached, or a stream or a
 * temporary file that refused the bytes.
 */
final class WriteFailed extends RuntimeException implements SpreadsheetException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
