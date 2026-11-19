<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Exception;

use RuntimeException;

/**
 * A document that cannot be exported: its format is not a word processing format, or its package cannot be written.
 */
final class ExportFailed extends RuntimeException implements DocumentImportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
