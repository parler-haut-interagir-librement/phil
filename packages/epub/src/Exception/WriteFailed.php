<?php

declare(strict_types=1);

namespace Phil\Epub\Exception;

use RuntimeException;

/**
 * The archive could not be written: the target cannot be created, or the ZIP library refused an entry.
 */
final class WriteFailed extends RuntimeException implements EpubException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
