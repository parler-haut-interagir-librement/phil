<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use RuntimeException;

/**
 * The conferencing server answered and refused the call, with the key it gave: a checksum it did not accept, a room it does not know.
 */
final class ProviderRefused extends RuntimeException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
