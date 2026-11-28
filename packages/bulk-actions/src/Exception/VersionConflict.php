<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use RuntimeException;
use Throwable;

/**
 * Thrown by an action when the item changed since it was read, around an optimistic lock exception for instance: the
 * item is reported as a conflict and the other items go on.
 */
final class VersionConflict extends RuntimeException implements BulkActionsException
{
    public static function create(?Throwable $previous = null): self
    {
        return new self('The item changed since it was read.', 0, $previous);
    }
}
