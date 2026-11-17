<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Exception;

use RuntimeException;

/**
 * The tuples were read or written outside any partition while the scope requires one: a request, a message or a
 * command that acts in no tenant of a multi-tenant application. Nothing was read or written.
 */
final class UnscopedTuples extends RuntimeException implements ResourcePermissionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
