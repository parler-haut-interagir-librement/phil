<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Exception;

use InvalidArgumentException;

/**
 * A file whose format is neither given nor recognised from its bytes or its name.
 */
final class UnsupportedFormat extends InvalidArgumentException implements DocumentImportException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
