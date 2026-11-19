<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Exception;

use RuntimeException;

/**
 * An archive larger, once uncompressed, than the limits of the importer allow: the guard against compression bombs.
 */
final class LimitExceeded extends RuntimeException implements DocumentImportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
