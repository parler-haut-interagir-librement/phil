<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Exception;

use Phil\PagedMedia\Exception\PagedMediaException;
use RuntimeException;
use Throwable;

/**
 * A rendered file the storage could not keep, or a job the job store could not keep: a path outside it, a directory
 * it cannot write, a pool that fails.
 */
final class StorageFailed extends RuntimeException implements PagedMediaException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, previous: $previous);
    }
}
