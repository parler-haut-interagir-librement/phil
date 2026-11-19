<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use RuntimeException;

/**
 * An answer of the conferencing server the library cannot read: not XML, too large, missing what the call returns.
 */
final class InvalidResponse extends RuntimeException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
