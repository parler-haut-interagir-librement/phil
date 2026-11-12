<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Exception;

use RuntimeException;

/**
 * A sheet that cannot be written: past a limit of its format, a cell of no kind the writer knows, a stream that refuses
 * to be written, or a format no writer writes.
 */
final class ExportRefused extends RuntimeException implements ExportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
