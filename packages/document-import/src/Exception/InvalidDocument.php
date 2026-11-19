<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Exception;

use RuntimeException;

/**
 * A file that is not what its format says: an archive that cannot be opened, a missing main part, XML that is not
 * well-formed or declares a document type.
 */
final class InvalidDocument extends RuntimeException implements DocumentImportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
