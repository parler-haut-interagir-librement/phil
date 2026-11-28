<?php

declare(strict_types=1);

namespace Phil\BulkActions\Exception;

use RuntimeException;

/**
 * A run identifier the run store does not know.
 */
final class RunNotFound extends RuntimeException implements BulkActionsException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No bulk run has the identifier "%s".', $id));
    }
}
