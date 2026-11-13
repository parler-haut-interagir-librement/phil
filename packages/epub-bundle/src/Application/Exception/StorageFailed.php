<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Application\Exception;

use Phil\Epub\Exception\EpubException;
use RuntimeException;
use Throwable;

/**
 * A book the storage could not keep: a path that leaves it, a directory or a file that cannot be written.
 */
final class StorageFailed extends RuntimeException implements EpubException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
